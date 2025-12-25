<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Listing.php';

$database = new Database();
$db = $database->getConnection();

$location = $_GET['location'] ?? '';
$category = $_GET['category'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;

if(!$location) {
    header('Location: choose-location.php');
    exit();
}

// Save current city to session
$_SESSION['current_city'] = $location;

// Get city info
$query = "SELECT * FROM cities WHERE name = :location LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute(['location' => $location]);
$city = $stmt->fetch();

if(!$city) {
    header('Location: choose-location.php');
    exit();
}

// Get categories
$query = "SELECT * FROM categories ORDER BY name ASC";
$stmt = $db->query($query);
$categories = $stmt->fetchAll();

// Check which columns exist
$query = "SHOW COLUMNS FROM listings";
$stmt = $db->query($query);
$existing_columns = [];
while($col = $stmt->fetch()) {
    $existing_columns[] = $col['Field'];
}

$has_is_deleted = in_array('is_deleted', $existing_columns);
$has_status = in_array('status', $existing_columns);

// Build WHERE clause
$where_conditions = ["l.city_id = :city_id"];
if($has_is_deleted) $where_conditions[] = "l.is_deleted = FALSE";
if($has_status) $where_conditions[] = "l.status = 'active'";
$where_clause = implode(' AND ', $where_conditions);

// Get listings
$offset = ($page - 1) * $per_page;

if($category) {
    $query = "SELECT l.*, c.name as category_name, u.username, u.is_premium 
              FROM listings l 
              LEFT JOIN categories c ON l.category_id = c.id 
              LEFT JOIN users u ON l.user_id = u.id 
              WHERE $where_clause AND c.slug = :category 
              ORDER BY l.is_featured DESC, l.created_at DESC 
              LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':city_id', $city['id'], PDO::PARAM_INT);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
} else {
    $query = "SELECT l.*, c.name as category_name, u.username, u.is_premium 
              FROM listings l 
              LEFT JOIN categories c ON l.category_id = c.id 
              LEFT JOIN users u ON l.user_id = u.id 
              WHERE $where_clause 
              ORDER BY l.is_featured DESC, l.created_at DESC 
              LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':city_id', $city['id'], PDO::PARAM_INT);
    $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
}

$stmt->execute();
$listings = $stmt->fetchAll();

// Get total count
if($category) {
    $query = "SELECT COUNT(*) FROM listings l 
              LEFT JOIN categories c ON l.category_id = c.id 
              WHERE $where_clause AND c.slug = :category";
    $stmt = $db->prepare($query);
    $stmt->execute(['city_id' => $city['id'], 'category' => $category]);
} else {
    $query = "SELECT COUNT(*) FROM listings l WHERE $where_clause";
    $stmt = $db->prepare($query);
    $stmt->execute(['city_id' => $city['id']]);
}

$total_listings = $stmt->fetchColumn();
$total_pages = ceil($total_listings / $per_page);

// Get nearby users (if logged in and has location)
$nearby_users = [];
if(isset($_SESSION['user_id'])) {
    $query = "SELECT current_latitude, current_longitude FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $my_location = $stmt->fetch();
    
    if($my_location && $my_location['current_latitude']) {
        $query = "SELECT u.id, u.username, u.is_premium, u.is_online,
                  (6371 * acos(cos(radians(:lat)) * cos(radians(current_latitude)) * 
                  cos(radians(current_longitude) - radians(:lng)) + 
                  sin(radians(:lat2)) * sin(radians(current_latitude)))) AS distance
                  FROM users u
                  WHERE u.id != :user_id 
                  AND u.current_latitude IS NOT NULL 
                  AND u.is_online = 1
                  HAVING distance < 50
                  ORDER BY distance ASC
                  LIMIT 12";
        $stmt = $db->prepare($query);
        $stmt->execute([
            'lat' => $my_location['current_latitude'],
            'lng' => $my_location['current_longitude'],
            'lat2' => $my_location['current_latitude'],
            'user_id' => $_SESSION['user_id']
        ]);
        $nearby_users = $stmt->fetchAll();
    }
}

include 'views/header.php';
?>

<!-- Hero Section -->
<div class="border-b border-gh-border bg-gh-bg">
  <div class="mx-auto max-w-7xl px-4 py-10">
    <div class="mx-auto max-w-3xl text-center">
      <h1 class="text-3xl font-extrabold tracking-tight md:text-4xl">
        <?php echo htmlspecialchars($city['name']); ?>
      </h1>
      <p class="mt-3 text-lg text-gh-muted">
        Discover <?php echo number_format($total_listings); ?> active listings in your area
      </p>

      <!-- Search Bar -->
      <form action="search.php" method="GET" class="mt-6">
        <div class="flex flex-col gap-3 rounded-xl border border-gh-border bg-gh-panel p-3 sm:flex-row sm:items-center">
          <input type="text" 
                 name="q" 
                 value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>"
                 placeholder="Search listings in <?php echo htmlspecialchars($city['name']); ?>..."
                 class="w-full flex-1 rounded-md border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-colors focus:outline-none focus:ring-2 focus:ring-gh-accent" />
          <input type="hidden" name="location" value="<?php echo htmlspecialchars($location); ?>" />
          <button type="submit"
                  class="inline-flex items-center justify-center gap-2 rounded-md bg-gh-accent px-6 py-2.5 text-sm font-semibold text-white transition-all hover:brightness-110">
            <i class="bi bi-search"></i>
            Search
          </button>
        </div>
      </form>

      <!-- Quick Filters -->
      <div class="mt-5 flex flex-wrap justify-center gap-2">
        <a href="?location=<?php echo urlencode($location); ?>"
           class="rounded-full border px-4 py-2 text-sm font-semibold transition-colors <?php echo empty($category) ? 'border-gh-accent bg-gh-accent text-white' : 'border-gh-border bg-gh-panel text-gh-muted hover:bg-white/5 hover:text-gh-fg'; ?>">
          All
        </a>
        <?php foreach(array_slice($categories, 0, 6) as $cat): ?>
          <a href="?location=<?php echo urlencode($location); ?>&category=<?php echo $cat['slug']; ?>"
             class="rounded-full border px-4 py-2 text-sm font-semibold transition-colors <?php echo ($category === $cat['slug']) ? 'border-gh-accent bg-gh-accent text-white' : 'border-gh-border bg-gh-panel text-gh-muted hover:bg-white/5 hover:text-gh-fg'; ?>">
            <?php echo htmlspecialchars($cat['name']); ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Main Content -->
<div class="mx-auto max-w-7xl px-4 py-8">
  <div class="space-y-6">

    <!-- Enable Location Banner -->
    <?php if(isset($_SESSION['user_id']) && empty($my_location['current_latitude'])): ?>
      <div class="rounded-xl border border-gh-border bg-gh-panel p-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <div class="flex items-center gap-2 text-base font-bold">
              <i class="bi bi-geo-alt-fill text-gh-accent"></i>
              Enable your location
            </div>
            <div class="mt-1 text-sm text-gh-muted">
              Share your location to see nearby users and get personalized recommendations.
            </div>
          </div>
          <button class="inline-flex items-center justify-center gap-2 rounded-md bg-gh-accent px-4 py-2.5 text-sm font-semibold text-white transition-all hover:brightness-110"
                  onclick="enableLocation()">
            <i class="bi bi-geo-alt"></i> Enable now
          </button>
        </div>
      </div>
    <?php endif; ?>

    <!-- Nearby Users -->
    <?php if(!empty($nearby_users)): ?>
      <div class="rounded-xl border border-gh-border bg-gh-panel p-5">
        <div class="flex items-center justify-between gap-4">
          <h2 class="flex items-center gap-2 text-lg font-bold">
            <i class="bi bi-people-fill text-gh-accent"></i>
            Nearby users
          </h2>
          <span class="rounded-full border border-gh-border bg-gh-panel2 px-3 py-1 text-xs font-semibold text-gh-success">
            <?php echo count($nearby_users); ?> online
          </span>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          <?php foreach($nearby_users as $user): ?>
            <a href="profile.php?id=<?php echo $user['id']; ?>"
               class="group rounded-lg border border-gh-border bg-gh-panel2 p-4 transition-colors hover:bg-white/5">
              <div class="flex items-center gap-3">
                <div class="relative">
                  <div class="flex h-12 w-12 items-center justify-center rounded-full bg-white/5 text-lg font-extrabold">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                  </div>
                  <span class="absolute -bottom-0.5 -right-0.5 h-3.5 w-3.5 rounded-full border-2 border-gh-panel2 bg-gh-success"></span>
                </div>
                <div class="min-w-0 flex-1">
                  <div class="truncate font-semibold group-hover:text-gh-accent">
                    <?php echo htmlspecialchars($user['username']); ?>
                    <?php if($user['is_premium']): ?>
                      <i class="bi bi-patch-check-fill ml-1 text-gh-warning"></i>
                    <?php endif; ?>
                  </div>
                  <div class="text-xs text-gh-muted">
                    <i class="bi bi-geo-alt-fill mr-1"></i><?php echo round($user['distance'], 1); ?> km away
                  </div>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Categories Grid (show only if no category selected) -->
    <?php if(empty($category)): ?>
      <div class="rounded-xl border border-gh-border bg-gh-panel p-5">
        <h2 class="flex items-center gap-2 text-lg font-bold">
          <i class="bi bi-grid-3x3-gap-fill text-gh-accent"></i>
          Browse by category
        </h2>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          <?php 
          $icons = [
            'women-seeking-men' => '👩',
            'men-seeking-women' => '👨',
            'couples' => '💑',
            'transgender' => '🏳️‍⚧️',
            'casual-encounters' => '✨',
            'other' => '🌟'
          ];
          
          foreach($categories as $cat): 
            // Get count for this category
            $count_query = "SELECT COUNT(*) FROM listings l 
                           LEFT JOIN categories c ON l.category_id = c.id 
                           WHERE l.city_id = :city_id AND c.id = :cat_id";
            if($has_is_deleted) $count_query .= " AND l.is_deleted = FALSE";
            if($has_status) $count_query .= " AND l.status = 'active'";
            $stmt = $db->prepare($count_query);
            $stmt->execute(['city_id' => $city['id'], 'cat_id' => $cat['id']]);
            $cat_count = $stmt->fetchColumn();
          ?>
            <a href="?location=<?php echo urlencode($location); ?>&category=<?php echo $cat['slug']; ?>"
               class="group rounded-lg border border-gh-border bg-gh-panel2 p-4 text-center transition-colors hover:bg-white/5">
              <div class="text-3xl"><?php echo $icons[$cat['slug']] ?? '📋'; ?></div>
              <div class="mt-3 font-semibold group-hover:text-gh-accent">
                <?php echo htmlspecialchars($cat['name']); ?>
              </div>
              <div class="mt-1 text-xs text-gh-muted">
                <?php echo number_format($cat_count); ?> listings
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Listings Section -->
    <div class="rounded-xl border border-gh-border bg-gh-panel p-5">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="flex items-center gap-2 text-lg font-bold">
          <i class="bi bi-fire text-gh-accent"></i>
          <?php echo $category ? htmlspecialchars(str_replace('-', ' ', ucwords($category, '-'))) : 'Latest listings'; ?>
        </h2>

        <div class="flex items-center gap-2">
          <select class="rounded-md border border-gh-border bg-gh-panel2 px-3 py-1.5 text-sm font-semibold text-gh-fg transition-colors focus:outline-none focus:ring-2 focus:ring-gh-accent"
                  onchange="window.location.href=this.value">
            <option value="?location=<?php echo urlencode($location); ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?>&sort=newest">Newest first</option>
            <option value="?location=<?php echo urlencode($location); ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?>&sort=popular">Most popular</option>
            <option value="?location=<?php echo urlencode($location); ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?>&sort=featured">Featured</option>
          </select>
        </div>
      </div>

      <?php if(empty($listings)): ?>
        <!-- Empty State -->
        <div class="mt-6 rounded-lg border border-gh-border bg-gh-panel2 p-12 text-center">
          <div class="text-5xl opacity-20">📭</div>
          <div class="mt-4 text-base font-bold">No listings found</div>
          <div class="mt-2 text-sm text-gh-muted">Be the first to post in this category!</div>
          <div class="mt-6">
            <?php if(isset($_SESSION['user_id'])): ?>
              <a href="post-ad.php" class="inline-flex items-center gap-2 rounded-md bg-gh-accent px-5 py-2.5 text-sm font-semibold text-white transition-all hover:brightness-110">
                <i class="bi bi-plus-circle"></i> Post first listing
              </a>
            <?php else: ?>
              <a href="login.php" class="inline-flex items-center gap-2 rounded-md bg-gh-accent px-5 py-2.5 text-sm font-semibold text-white transition-all hover:brightness-110">
                <i class="bi bi-box-arrow-in-right"></i> Login to post
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php else: ?>
        <!-- Listings Grid -->
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <?php foreach($listings as $listing): ?>
            <a href="listing.php?id=<?php echo $listing['id']; ?>"
               class="group overflow-hidden rounded-xl border border-gh-border bg-gh-panel2 transition-colors hover:bg-white/5">
              
              <!-- Image -->
              <div class="relative aspect-video w-full overflow-hidden bg-black/20">
                <?php if(!empty($listing['photo_url'])): ?>
                  <img src="<?php echo htmlspecialchars($listing['photo_url']); ?>" 
                       alt="<?php echo htmlspecialchars($listing['title']); ?>" 
                       class="h-full w-full object-cover" />
                <?php else: ?>
                  <div class="flex h-full w-full items-center justify-center">
                    <i class="bi bi-image text-5xl text-gh-muted opacity-20"></i>
                  </div>
                <?php endif; ?>
                
                <?php if(!empty($listing['is_featured'])): ?>
                  <span class="absolute right-2 top-2 rounded-full border border-gh-border bg-gh-panel px-2.5 py-1 text-xs font-bold text-gh-warning backdrop-blur">
                    <i class="bi bi-star-fill mr-1"></i>Featured
                  </span>
                <?php endif; ?>
              </div>

              <!-- Content -->
              <div class="p-4">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0 flex-1">
                    <div class="truncate font-semibold group-hover:text-gh-accent">
                      <?php echo htmlspecialchars($listing['title']); ?>
                    </div>
                    <div class="mt-1 flex items-center gap-2 text-xs text-gh-muted">
                      <span class="rounded-full border border-gh-border bg-gh-panel px-2 py-0.5">
                        <?php echo htmlspecialchars($listing['category_name']); ?>
                      </span>
                      <span>•</span>
                      <span><?php echo htmlspecialchars($city['name']); ?></span>
                    </div>
                  </div>
                </div>

                <div class="mt-3 line-clamp-2 text-sm text-gh-muted">
                  <?php 
                    $excerpt = strip_tags($listing['description']);
                    echo htmlspecialchars(substr($excerpt, 0, 120)) . (strlen($excerpt) > 120 ? '...' : '');
                  ?>
                </div>

                <div class="mt-4 flex items-center justify-between border-t border-gh-border pt-3 text-xs text-gh-muted">
                  <div class="flex items-center gap-2">
                    <div class="flex h-6 w-6 items-center justify-center rounded-full bg-white/5 text-xs font-bold">
                      <?php echo strtoupper(substr($listing['username'], 0, 1)); ?>
                    </div>
                    <span class="font-semibold"><?php echo htmlspecialchars($listing['username']); ?></span>
                    <?php if($listing['is_premium']): ?>
                      <i class="bi bi-patch-check-fill text-gh-warning"></i>
                    <?php endif; ?>
                  </div>
                  <div class="flex items-center gap-3">
                    <span>
                      <i class="bi bi-eye-fill mr-1"></i><?php echo number_format($listing['views'] ?? 0); ?>
                    </span>
                    <span>
                      <i class="bi bi-clock-fill mr-1"></i>
                      <?php 
                        $diff = time() - strtotime($listing['created_at']);
                        if($diff < 3600) echo floor($diff / 60) . 'm';
                        elseif($diff < 86400) echo floor($diff / 3600) . 'h';
                        else echo floor($diff / 86400) . 'd';
                      ?>
                    </span>
                  </div>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
          <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
            <?php if($page > 1): ?>
              <a class="inline-flex items-center gap-2 rounded-md border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold transition-colors hover:bg-white/5"
                 href="?location=<?php echo urlencode($location); ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?>&page=<?php echo $page - 1; ?>">
                <i class="bi bi-chevron-left"></i> Previous
              </a>
            <?php endif; ?>

            <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
              <a class="rounded-md border px-4 py-2 text-sm font-semibold transition-colors <?php echo ($i === $page) ? 'border-transparent bg-gh-accent text-white' : 'border-gh-border bg-gh-panel text-gh-fg hover:bg-white/5'; ?>"
                 href="?location=<?php echo urlencode($location); ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?>&page=<?php echo $i; ?>">
                <?php echo $i; ?>
              </a>
            <?php endfor; ?>

            <?php if($page < $total_pages): ?>
              <a class="inline-flex items-center gap-2 rounded-md border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold transition-colors hover:bg-white/5"
                 href="?location=<?php echo urlencode($location); ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?>&page=<?php echo $page + 1; ?>">
                Next <i class="bi bi-chevron-right"></i>
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- Floating Action Button -->
<?php if(isset($_SESSION['user_id'])): ?>
  <a href="post-ad.php"
     class="fixed bottom-24 right-5 z-40 inline-flex h-14 w-14 items-center justify-center rounded-full bg-gh-accent text-white shadow-2xl transition-all hover:scale-110 hover:brightness-110 lg:bottom-8">
    <i class="bi bi-plus-lg text-xl"></i>
  </a>
<?php endif; ?>

<script>
function enableLocation() {
  if(!navigator.geolocation) {
    alert('Geolocation not supported');
    return;
  }
  
  navigator.geolocation.getCurrentPosition(
    position => {
      const formData = new FormData();
      formData.append('action', 'update_location');
      formData.append('latitude', position.coords.latitude);
      formData.append('longitude', position.coords.longitude);
      
      fetch('api/location.php', {
        method: 'POST',
        body: formData
      })
      .then(r => r.json())
      .then(data => {
        if(data.success) location.reload();
      })
      .catch(err => console.error(err));
    },
    error => alert('Unable to get location')
  );
}
</script>

<?php include 'views/footer.php'; ?>
