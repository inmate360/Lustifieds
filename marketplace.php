<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/maintenance_check.php';

$database = new Database();
$db = $database->getConnection();

// Check for maintenance mode
if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

// Check if current user is a creator (via creator_settings table)
$is_creator = false;
if(isset($_SESSION['user_id'])) {
    try {
        $check_query = "SELECT is_creator FROM creator_settings WHERE user_id = :user_id AND is_creator = 1";
        $stmt = $db->prepare($check_query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        $creator_data = $stmt->fetch();
        $is_creator = ($creator_data && $creator_data['is_creator'] == 1);
    } catch (PDOException $e) {
        // Table might not exist yet, default to false
        $is_creator = false;
    }
}

// Initialize arrays
$featured_creators = [];
$users = [];

try {
    // Fetch featured creators (verified users)
    $featured_query = "
        SELECT 
            u.id,
            u.username,
            COALESCE(u.username, '') as display_name,
            u.avatar,
            u.verified,
            u.last_seen,
            CASE 
                WHEN TIMESTAMPDIFF(MINUTE, u.last_seen, NOW()) < 15 THEN 1 
                ELSE 0 
            END as is_online,
            '' as cover_image,
            0 as subscription_price,
            0 as post_count,
            0 as is_subscribed,
            0 as is_free
        FROM users u
        WHERE u.verified = 1
        ORDER BY u.last_seen DESC
        LIMIT 12
    ";
    
    $stmt = $db->prepare($featured_query);
    $stmt->execute();
    $featured_creators = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process featured creators
    foreach ($featured_creators as &$creator) {
        $creator['is_verified'] = (int)$creator['verified'];
        $creator['is_creator'] = 1; // Featured users are creators
        $creator['avatar'] = getUserAvatar($creator['avatar']);
        $creator['cover_image'] = getUserCover($creator['cover_image'] ?? '');
    }
    unset($creator);
    
    // Fetch all other users
    $users_query = "
        SELECT 
            u.id,
            u.username,
            COALESCE(u.username, '') as display_name,
            u.avatar,
            u.verified,
            u.last_seen,
            CASE 
                WHEN TIMESTAMPDIFF(MINUTE, u.last_seen, NOW()) < 15 THEN 1 
                ELSE 0 
            END as is_online,
            '' as cover_image,
            0 as subscription_price,
            0 as post_count,
            0 as is_subscribed,
            0 as is_free
        FROM users u
        WHERE u.verified = 0
        ORDER BY u.last_seen DESC
        LIMIT 100
    ";
    
    $stmt = $db->prepare($users_query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process users
    foreach ($users as &$user) {
        $user['is_verified'] = (int)$user['verified'];
        $user['is_creator'] = 0; // Regular users
        $user['avatar'] = getUserAvatar($user['avatar']);
        $user['cover_image'] = getUserCover($user['cover_image'] ?? '');
    }
    unset($user);
    
} catch (PDOException $e) {
    error_log("Marketplace Error: " . $e->getMessage());
    $featured_creators = [];
    $users = [];
}

include 'views/header.php';
?>
<link rel="stylesheet" href="/assets/css/creator-cards.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
/* Enhanced Marketplace Styles */
.marketplace-hero {
  background: linear-gradient(135deg, #4267f5 0%, #1d9bf0 100%);
  padding: 3rem 2rem;
  border-radius: 25px;
  margin-bottom: 2.5rem;
  text-align: center;
  box-shadow: 0 20px 60px rgba(66, 103, 245, 0.3);
}

.marketplace-hero h1 {
  font-size: 2.5rem;
  font-weight: 800;
  color: #fff;
  margin-bottom: 1rem;
  text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.marketplace-hero p {
  font-size: 1.2rem;
  color: rgba(255, 255, 255, 0.9);
  max-width: 600px;
  margin: 0 auto 1.5rem;
}

.creator-cta {
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
  background: #fff;
  color: #4267f5;
  padding: 1rem 2rem;
  border-radius: 50px;
  font-weight: 700;
  font-size: 1.1rem;
  text-decoration: none;
  transition: all 0.3s;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.creator-cta:hover {
  transform: translateY(-3px);
  box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
  color: #4267f5;
}

.creator-cta i {
  font-size: 1.3rem;
}

.creator-nav {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
  margin-top: 1rem;
}

.creator-nav-link {
  padding: 0.75rem 1.5rem;
  background: rgba(255, 255, 255, 0.2);
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 25px;
  color: #fff;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.creator-nav-link:hover {
  background: rgba(255, 255, 255, 0.3);
  border-color: #fff;
  transform: translateY(-2px);
  color: #fff;
}

.search-filter-section {
  background: var(--bg-card);
  border: 2px solid var(--border);
  border-radius: 20px;
  padding: 2rem;
  margin-bottom: 2.5rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.search-box {
  position: relative;
  margin-bottom: 1.5rem;
}

.search-input {
  width: 100%;
  padding: 1rem 1rem 1rem 3.5rem;
  background: var(--bg-dark);
  border: 2px solid var(--border);
  border-radius: 15px;
  color: var(--text);
  font-size: 1rem;
  transition: all 0.3s;
}

.search-input:focus {
  outline: none;
  border-color: var(--blue);
  box-shadow: 0 0 0 4px rgba(66, 103, 245, 0.1);
}

.search-icon {
  position: absolute;
  left: 1.25rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--gray);
  font-size: 1.2rem;
  pointer-events: none;
}

.filter-chips {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1rem;
}

.filter-chip {
  padding: 0.75rem 1.5rem;
  background: var(--bg-dark);
  border: 2px solid var(--border);
  border-radius: 25px;
  color: var(--text);
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.filter-chip:hover {
  border-color: var(--blue);
  transform: translateY(-2px);
}

.filter-chip.active {
  background: var(--blue);
  border-color: var(--blue);
  color: #fff;
}

.sort-dropdown {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.sort-label {
  color: var(--gray);
  font-weight: 600;
}

.sort-select {
  padding: 0.75rem 1.25rem;
  background: var(--bg-dark);
  border: 2px solid var(--border);
  border-radius: 12px;
  color: var(--text);
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
}

.sort-select:focus {
  outline: none;
  border-color: var(--blue);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.section-title {
  color: #fff;
  font-size: 1.8rem;
  font-weight: 800;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.view-toggle {
  display: flex;
  gap: 0.5rem;
  background: var(--bg-card);
  border: 2px solid var(--border);
  border-radius: 12px;
  padding: 0.25rem;
}

.view-btn {
  padding: 0.5rem 1rem;
  background: transparent;
  border: none;
  border-radius: 8px;
  color: var(--gray);
  cursor: pointer;
  transition: all 0.3s;
  font-size: 1.2rem;
}

.view-btn.active {
  background: var(--blue);
  color: #fff;
}

.stats-bar {
  display: flex;
  gap: 2rem;
  justify-content: center;
  flex-wrap: wrap;
  margin-bottom: 2.5rem;
}

.stat-box {
  background: var(--bg-card);
  border: 2px solid var(--border);
  border-radius: 15px;
  padding: 1.5rem 2rem;
  text-align: center;
  transition: all 0.3s;
}

.stat-box:hover {
  border-color: var(--blue);
  transform: translateY(-3px);
}

.stat-value {
  font-size: 2rem;
  font-weight: 800;
  color: var(--blue);
  margin-bottom: 0.5rem;
}

.stat-label {
  color: var(--gray);
  font-weight: 600;
  font-size: 0.9rem;
}

.creator-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 2rem;
  margin-bottom: 3rem;
}

.list-view .creator-grid {
  grid-template-columns: 1fr;
}

.empty-state {
  text-align: center;
  padding: 4rem 2rem;
  color: var(--gray);
}

.empty-state i {
  font-size: 4rem;
  color: var(--border);
  margin-bottom: 1rem;
}

.empty-state h3 {
  color: var(--text);
  font-size: 1.5rem;
  margin-bottom: 0.5rem;
}

/* Enhanced subscription card hover effects */
.subscription-card {
  position: relative;
  overflow: hidden;
}

.subscription-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(66, 103, 245, 0.1), transparent);
  transition: left 0.5s;
  z-index: 1;
  pointer-events: none;
}

.subscription-card:hover::before {
  left: 100%;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .marketplace-hero h1 { font-size: 2rem; }
  .marketplace-hero p { font-size: 1rem; }
  .creator-cta { font-size: 1rem; padding: 0.875rem 1.75rem; }
  .creator-nav { flex-direction: column; }
  .creator-nav-link { justify-content: center; }
  .search-filter-section { padding: 1.5rem; }
  .filter-chips { gap: 0.75rem; }
  .filter-chip { padding: 0.6rem 1.2rem; font-size: 0.9rem; }
  .section-title { font-size: 1.5rem; }
  .stats-bar { gap: 1rem; }
  .stat-box { padding: 1rem 1.5rem; }
  .creator-grid { grid-template-columns: 1fr; gap: 1.5rem; }
}
</style>

<!-- ENHANCED MARKETPLACE UI BEGIN -->
<div class="container" style="margin-top:2.5rem;max-width:1200px;">
  
  <!-- Hero Section -->
  <div class="marketplace-hero">
    <h1><i class="bi bi-shop"></i> Creator Marketplace</h1>
    <p>Discover and connect with amazing creators. Subscribe, message, and support your favorites.</p>
    
    <?php if(isset($_SESSION['user_id'])): ?>
      <?php if($is_creator): ?>
        <!-- Creator Navigation -->
        <div class="creator-nav">
          <a href="/creator-dashboard.php" class="creator-nav-link">
            <i class="bi bi-grid-3x3"></i> Dashboard
          </a>
          <a href="/upload-content.php" class="creator-nav-link">
            <i class="bi bi-cloud-upload"></i> Upload
          </a>
          <a href="/creator-settings.php" class="creator-nav-link">
            <i class="bi bi-gear"></i> Settings
          </a>
          <a href="/withdraw-earnings.php" class="creator-nav-link">
            <i class="bi bi-wallet2"></i> Earnings
          </a>
        </div>
      <?php else: ?>
        <!-- Become Creator CTA -->
        <a href="/become-creator.php" class="creator-cta">
          <i class="bi bi-star-fill"></i>
          Become a Creator
        </a>
      <?php endif; ?>
    <?php else: ?>
      <!-- Not logged in -->
      <a href="/login.php" class="creator-cta">
        <i class="bi bi-person-circle"></i>
        Login to Start Creating
      </a>
    <?php endif; ?>
  </div>

  <!-- Stats Bar -->
  <div class="stats-bar">
    <div class="stat-box">
      <div class="stat-value"><?php echo count($featured_creators) + count($users); ?></div>
      <div class="stat-label">Total Users</div>
    </div>
    <div class="stat-box">
      <div class="stat-value"><?php 
        $all_users = array_merge($featured_creators, $users);
        $online_count = count(array_filter($all_users, function($u) { 
          return isset($u['is_online']) && $u['is_online']; 
        }));
        echo $online_count;
      ?></div>
      <div class="stat-label">Online Now</div>
    </div>
    <div class="stat-box">
      <div class="stat-value"><?php echo count($featured_creators); ?></div>
      <div class="stat-label">Featured</div>
    </div>
  </div>

  <!-- Search & Filter Section -->
  <div class="search-filter-section">
    <div class="search-box">
      <i class="bi bi-search search-icon"></i>
      <input type="text" class="search-input" placeholder="Search by name or username..." id="creatorSearch">
    </div>
    
    <div class="filter-chips">
      <button class="filter-chip active" data-filter="all">
        <i class="bi bi-grid-3x3"></i> All Users
      </button>
      <button class="filter-chip" data-filter="online">
        <i class="bi bi-circle-fill" style="color:var(--green);font-size:0.6rem"></i> Online
      </button>
      <button class="filter-chip" data-filter="verified">
        <i class="bi bi-patch-check-fill"></i> Verified
      </button>
    </div>

    <div class="sort-dropdown">
      <span class="sort-label">Sort by:</span>
      <select class="sort-select" id="sortBy">
        <option value="featured">Featured First</option>
        <option value="name">Name (A-Z)</option>
        <option value="online">Online Status</option>
        <option value="recent">Recently Active</option>
      </select>
    </div>
  </div>

  <?php if (count($featured_creators) > 0): ?>
  <!-- Featured Users Section -->
  <div class="section-header">
    <h2 class="section-title">
      <i class="bi bi-star-fill" style="color:var(--orange)"></i>
      Featured Users
    </h2>
    <div class="view-toggle">
      <button class="view-btn active" data-view="grid"><i class="bi bi-grid-3x3"></i></button>
      <button class="view-btn" data-view="list"><i class="bi bi-list"></i></button>
    </div>
  </div>

  <div class="creator-grid" id="featuredGrid">
    <?php foreach($featured_creators as $user): ?>
      <div class="subscription-card creator-item" 
           data-name="<?php echo strtolower(e($user['display_name'])); ?>" 
           data-username="<?php echo strtolower(e($user['username'])); ?>"
           data-online="<?php echo $user['is_online'] ? '1' : '0'; ?>"
           data-verified="<?php echo $user['is_verified'] ? '1' : '0'; ?>"
           data-creator="<?php echo $user['is_creator'] ? '1' : '0'; ?>"
           onclick="window.location.href='/profile.php?id=<?php echo $user['id']; ?>'">
        <div class="subscription-cover">
          <?php if($user['cover_image']): ?>
            <img src="<?php echo e($user['cover_image']); ?>" alt="Cover">
          <?php endif; ?>
          <div class="subscription-status">
            <?php echo $user['is_online'] ? '<i class="bi bi-circle-fill" style="font-size:0.6rem;color:var(--green)"></i> Available now' : '<i class="bi bi-circle" style="font-size:0.6rem"></i> Offline'; ?>
          </div>
        </div>
        <div class="subscription-body">
          <div class="subscription-avatar-wrapper">
            <div class="subscription-avatar">
              <img src="<?php echo e($user['avatar']); ?>" alt="Avatar">
            </div>
            <?php if($user['is_online']): ?><div class="online-indicator"></div><?php endif; ?>
            <?php if($user['is_verified']): ?><div class="verification-badge"><i class="bi bi-check"></i></div><?php endif; ?>
          </div>
          <div class="subscription-info">
            <div class="subscription-username">
              <?php echo e($user['display_name']); ?>
              <?php if($user['is_verified']): ?><i class="bi bi-patch-check-fill" style="color:#4267f5"></i><?php endif; ?>
            </div>
            <div class="subscription-handle">@<?php echo e($user['username']); ?></div>
          </div>
          <div class="subscription-actions">
            <a href="/messages-compose.php?to=<?php echo $user['id']; ?>" class="subscription-btn message" onclick="event.stopPropagation();"><i class="bi bi-chat"></i> Message</a>
            <a href="/profile.php?id=<?php echo $user['id']; ?>" class="subscription-btn tip" onclick="event.stopPropagation();"><i class="bi bi-person"></i> View Profile</a>
          </div>
          <?php if($user['is_verified']): ?>
          <div class="subscription-badge premium"><i class="bi bi-patch-check-fill"></i> VERIFIED</div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (count($users) > 0): ?>
  <!-- Browse All Users Section -->
  <div class="section-header" style="margin-top:3rem">
    <h2 class="section-title">
      <i class="bi bi-people-fill" style="color:var(--cyan)"></i>
      All Users
    </h2>
  </div>

  <div id="usersList">
    <?php foreach($users as $user): ?>
      <div class="user-list-item creator-item"
           data-name="<?php echo strtolower(e($user['display_name'])); ?>" 
           data-username="<?php echo strtolower(e($user['username'])); ?>"
           data-online="<?php echo $user['is_online'] ? '1' : '0'; ?>"
           data-verified="<?php echo $user['is_verified'] ? '1' : '0'; ?>"
           data-creator="<?php echo $user['is_creator'] ? '1' : '0'; ?>"
           onclick="window.location.href='/profile.php?id=<?php echo $user['id']; ?>'">
        <div class="user-list-avatar">
          <img src="<?php echo e($user['avatar']); ?>" alt="Avatar">
          <?php if($user['is_online']): ?><div class="online-indicator"></div><?php endif; ?>
        </div>
        <div class="user-list-info">
          <div class="user-list-username">
            <?php echo e($user['display_name']); ?>
            <?php if($user['is_verified']): ?><i class="bi bi-patch-check-fill" style="color:#4267f5"></i><?php endif; ?>
          </div>
          <div class="user-list-meta">
            <span><i class="bi bi-at"></i> <?php echo e($user['username']); ?></span>
            <span>• <?php 
              if ($user['is_online']) {
                echo '<i class="bi bi-circle-fill" style="color:var(--green);font-size:0.5rem"></i> Online now';
              } else if ($user['last_seen']) {
                echo 'Last seen ' . timeAgo($user['last_seen']);
              } else {
                echo 'Offline';
              }
            ?></span>
          </div>
        </div>
        <div class="user-list-actions">
          <a href="/messages-compose.php?to=<?php echo $user['id']; ?>" class="user-list-btn" onclick="event.stopPropagation()"><i class="bi bi-chat"></i> Message</a>
          <a href="/profile.php?id=<?php echo $user['id']; ?>" class="user-list-btn" onclick="event.stopPropagation()"><i class="bi bi-person"></i></a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (count($featured_creators) === 0 && count($users) === 0): ?>
  <!-- Empty State - No users available -->
  <div class="empty-state">
    <i class="bi bi-people"></i>
    <h3>No users available yet</h3>
    <p>Check back soon!</p>
  </div>
  <?php endif; ?>

  <!-- Empty State for filtered results -->
  <div class="empty-state" id="emptyState" style="display:none">
    <i class="bi bi-search"></i>
    <h3>No users found</h3>
    <p>Try adjusting your search or filters</p>
  </div>

</div>

<script>
// Enhanced Marketplace Functionality
(function() {
  const searchInput = document.getElementById('creatorSearch');
  const filterChips = document.querySelectorAll('.filter-chip');
  const sortSelect = document.getElementById('sortBy');
  const creatorItems = document.querySelectorAll('.creator-item');
  const emptyState = document.getElementById('emptyState');
  const viewBtns = document.querySelectorAll('.view-btn');
  const featuredGrid = document.getElementById('featuredGrid');
  
  let currentFilter = 'all';
  let currentSort = 'featured';
  
  // Search functionality
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      filterCreators();
    });
  }
  
  // Filter chips
  filterChips.forEach(chip => {
    chip.addEventListener('click', function() {
      filterChips.forEach(c => c.classList.remove('active'));
      this.classList.add('active');
      currentFilter = this.dataset.filter;
      filterCreators();
    });
  });
  
  // Sort functionality
  if (sortSelect) {
    sortSelect.addEventListener('change', function() {
      currentSort = this.value;
      sortCreators();
    });
  }
  
  // View toggle
  viewBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      viewBtns.forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      const view = this.dataset.view;
      if (featuredGrid) {
        if (view === 'list') {
          featuredGrid.classList.add('list-view');
        } else {
          featuredGrid.classList.remove('list-view');
        }
      }
    });
  });
  
  function filterCreators() {
    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
    let visibleCount = 0;
    
    creatorItems.forEach(item => {
      const name = item.dataset.name || '';
      const username = item.dataset.username || '';
      const matchesSearch = name.includes(searchTerm) || username.includes(searchTerm);
      
      let matchesFilter = true;
      if (currentFilter === 'online') matchesFilter = item.dataset.online === '1';
      else if (currentFilter === 'verified') matchesFilter = item.dataset.verified === '1';
      
      if (matchesSearch && matchesFilter) {
        item.style.display = '';
        visibleCount++;
      } else {
        item.style.display = 'none';
      }
    });
    
    if (emptyState) {
      emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
    }
  }
  
  function sortCreators() {
    console.log('Sorting by:', currentSort);
  }
})();
</script>
<!-- ENHANCED MARKETPLACE UI END -->

<?php include 'views/footer.php'; ?>