<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Listing.php';
require_once 'classes/Favorites.php';

$database = new Database();
$db = $database->getConnection();

$listing_id = (int)($_GET['id'] ?? 0);

if(!$listing_id) {
    header('Location: index.php');
    exit();
}

// Get listing with user details
$query = "SELECT l.*, c.name as category_name, ct.name as city_name, 
          u.username, u.created_at as user_created, u.is_online, u.last_seen,
          u.is_premium
          FROM listings l
          LEFT JOIN categories c ON l.category_id = c.id
          LEFT JOIN cities ct ON l.city_id = ct.id
          LEFT JOIN users u ON l.user_id = u.id
          WHERE l.id = :listing_id
          LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':listing_id', $listing_id);
$stmt->execute();
$listing = $stmt->fetch();

if(!$listing) {
    header('Location: index.php');
    exit();
}

// Increment view count
$listingObj = new Listing($db);
$listingObj->incrementViews($listing_id);

// Check if favorited
$is_favorited = false;
if(isset($_SESSION['user_id'])) {
    $favorites = new Favorites($db);
    $is_favorited = $favorites->isFavorited($_SESSION['user_id'], $listing_id);
}

$is_own_listing = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $listing['user_id'];

include 'views/header.php';
?>

<div class="mx-auto max-w-7xl px-4 py-8">
  
  <!-- Breadcrumb -->
  <div class="mb-6 flex items-center gap-2 text-sm text-gh-muted">
    <a href="choose-location.php" class="transition-colors hover:text-gh-fg hover:underline">Home</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <a href="city.php?location=<?php echo urlencode($listing['city_name']); ?>" class="transition-colors hover:text-gh-fg hover:underline">
      <?php echo htmlspecialchars($listing['city_name']); ?>
    </a>
    <i class="bi bi-chevron-right text-xs"></i>
    <span class="text-gh-fg"><?php echo htmlspecialchars($listing['category_name']); ?></span>
  </div>

  <!-- Listing Header -->
  <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <h1 class="text-2xl font-extrabold tracking-tight md:text-3xl">
            <?php echo htmlspecialchars($listing['title']); ?>
          </h1>
          <?php if(!empty($listing['is_featured'])): ?>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-gh-border bg-gh-panel2 px-3 py-1 text-xs font-bold text-gh-warning">
              <i class="bi bi-star-fill"></i> Featured
            </span>
          <?php endif; ?>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-gh-muted">
          <span class="inline-flex items-center gap-1.5">
            <i class="bi bi-geo-alt-fill"></i>
            <?php echo htmlspecialchars($listing['city_name']); ?>
          </span>
          <span>•</span>
          <span class="inline-flex items-center gap-1.5 rounded-full border border-gh-border bg-gh-panel2 px-3 py-1">
            <?php echo htmlspecialchars($listing['category_name']); ?>
          </span>
          <span>•</span>
          <span class="inline-flex items-center gap-1.5">
            <i class="bi bi-eye-fill"></i>
            <?php echo number_format($listing['views'] ?? 0); ?> views
          </span>
          <span>•</span>
          <span class="inline-flex items-center gap-1.5">
            <i class="bi bi-clock-fill"></i>
            <?php 
              $time_diff = time() - strtotime($listing['created_at']);
              if($time_diff < 3600) echo floor($time_diff / 60) . ' minutes ago';
              elseif($time_diff < 86400) echo floor($time_diff / 3600) . ' hours ago';
              else echo floor($time_diff / 86400) . ' days ago';
            ?>
          </span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-wrap gap-2">
        <?php if(isset($_SESSION['user_id']) && !$is_own_listing): ?>
          <button id="favoriteBtn"
                  onclick="toggleFavorite(<?php echo $listing_id; ?>)"
                  class="inline-flex items-center justify-center gap-2 rounded-md border border-gh-border bg-gh-panel px-4 py-2.5 text-sm font-semibold transition-colors hover:bg-white/5 <?php echo $is_favorited ? 'text-gh-warning' : 'text-gh-fg'; ?>">
            <i class="bi <?php echo $is_favorited ? 'bi-star-fill' : 'bi-star'; ?>"></i>
            <span class="hidden sm:inline"><?php echo $is_favorited ? 'Saved' : 'Save'; ?></span>
          </button>

          <a href="messages-chat-simple.php?to=<?php echo urlencode($listing['username']); ?>"
             class="inline-flex items-center justify-center gap-2 rounded-md bg-gh-accent px-5 py-2.5 text-sm font-semibold text-white transition-all hover:brightness-110">
            <i class="bi bi-chat-dots-fill"></i> Send message
          </a>
        <?php elseif($is_own_listing): ?>
          <a href="edit-listing.php?id=<?php echo $listing_id; ?>"
             class="inline-flex items-center justify-center gap-2 rounded-md border border-gh-border bg-gh-panel px-4 py-2.5 text-sm font-semibold transition-colors hover:bg-white/5">
            <i class="bi bi-pencil-fill"></i> Edit
          </a>
          <button onclick="if(confirm('Delete this listing?')) window.location.href='delete-listing.php?id=<?php echo $listing_id; ?>'"
                  class="inline-flex items-center justify-center gap-2 rounded-md border border-gh-border bg-gh-panel px-4 py-2.5 text-sm font-semibold text-gh-danger transition-colors hover:bg-white/5">
            <i class="bi bi-trash-fill"></i> Delete
          </button>
        <?php else: ?>
          <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
             class="inline-flex items-center justify-center gap-2 rounded-md bg-gh-accent px-5 py-2.5 text-sm font-semibold text-white transition-all hover:brightness-110">
            <i class="bi bi-box-arrow-in-right"></i> Login to contact
          </a>
        <?php endif; ?>

        <button onclick="shareUrl()"
                class="inline-flex items-center justify-center gap-2 rounded-md border border-gh-border bg-gh-panel px-4 py-2.5 text-sm font-semibold transition-colors hover:bg-white/5">
          <i class="bi bi-share-fill"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- Main Content Grid -->
  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    
    <!-- Main Column (2/3) -->
    <div class="space-y-6 lg:col-span-2">
      
      <!-- Photo -->
      <?php if(!empty($listing['photo_url'])): ?>
        <div class="overflow-hidden rounded-xl border border-gh-border bg-gh-panel">
          <img src="<?php echo htmlspecialchars($listing['photo_url']); ?>" 
               alt="<?php echo htmlspecialchars($listing['title']); ?>" 
               class="w-full max-h-[600px] object-cover" />
        </div>
      <?php endif; ?>

      <!-- Description -->
      <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
        <h2 class="flex items-center gap-2 text-base font-bold">
          <i class="bi bi-file-text-fill text-gh-accent"></i>
          Description
        </h2>
        <div class="mt-4 whitespace-pre-line text-sm leading-relaxed text-gh-fg/90">
          <?php echo nl2br(htmlspecialchars($listing['description'])); ?>
        </div>
      </div>

      <!-- Listing Details -->
      <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
        <h2 class="flex items-center gap-2 text-base font-bold">
          <i class="bi bi-info-circle-fill text-gh-accent"></i>
          Listing details
        </h2>
        
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <div class="rounded-lg border border-gh-border bg-gh-panel2 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-gh-muted">Location</div>
            <div class="mt-1 font-semibold"><?php echo htmlspecialchars($listing['city_name']); ?></div>
          </div>

          <div class="rounded-lg border border-gh-border bg-gh-panel2 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-gh-muted">Category</div>
            <div class="mt-1 font-semibold"><?php echo htmlspecialchars($listing['category_name']); ?></div>
          </div>

          <div class="rounded-lg border border-gh-border bg-gh-panel2 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-gh-muted">Posted</div>
            <div class="mt-1 font-semibold"><?php echo date('M j, Y', strtotime($listing['created_at'])); ?></div>
          </div>

          <div class="rounded-lg border border-gh-border bg-gh-panel2 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-gh-muted">Listing ID</div>
            <div class="mt-1 font-mono text-sm font-semibold">#<?php echo $listing_id; ?></div>
          </div>
        </div>
      </div>

    </div>

    <!-- Sidebar (1/3) -->
    <div class="space-y-6">
      
      <!-- Posted By -->
      <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
        <h3 class="flex items-center gap-2 text-base font-bold">
          <i class="bi bi-person-circle text-gh-accent"></i>
          Posted by
        </h3>

        <a href="profile.php?id=<?php echo $listing['user_id']; ?>" 
           class="mt-4 block group">
          <div class="flex items-center gap-3">
            <div class="relative">
              <div class="flex h-14 w-14 items-center justify-center rounded-full border-2 border-gh-border bg-gh-panel2 text-xl font-extrabold">
                <?php echo strtoupper(substr($listing['username'], 0, 1)); ?>
              </div>
              <?php if(!empty($listing['is_online'])): ?>
                <span class="absolute -bottom-0.5 -right-0.5 h-4 w-4 rounded-full border-2 border-gh-panel bg-gh-success"></span>
              <?php endif; ?>
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2">
                <span class="truncate font-bold group-hover:text-gh-accent">
                  <?php echo htmlspecialchars($listing['username']); ?>
                </span>
                <?php if(!empty($listing['is_premium'])): ?>
                  <i class="bi bi-patch-check-fill text-gh-warning"></i>
                <?php endif; ?>
              </div>
              <div class="text-xs text-gh-muted">
                <?php if(!empty($listing['is_online'])): ?>
                  <span class="text-gh-success">● Online now</span>
                <?php elseif(!empty($listing['last_seen'])): ?>
                  Last seen <?php 
                    $last_seen_diff = time() - strtotime($listing['last_seen']);
                    if($last_seen_diff < 3600) echo floor($last_seen_diff / 60) . 'm ago';
                    elseif($last_seen_diff < 86400) echo floor($last_seen_diff / 3600) . 'h ago';
                    else echo floor($last_seen_diff / 86400) . 'd ago';
                  ?>
                <?php else: ?>
                  Offline
                <?php endif; ?>
              </div>
            </div>
          </div>
        </a>

        <div class="mt-4 space-y-2 text-sm">
          <div class="flex items-center justify-between text-gh-muted">
            <span>Member since</span>
            <span class="font-semibold text-gh-fg">
              <?php echo date('M Y', strtotime($listing['user_created'])); ?>
            </span>
          </div>
        </div>

        <?php if(isset($_SESSION['user_id']) && !$is_own_listing): ?>
          <a href="messages-chat-simple.php?to=<?php echo urlencode($listing['username']); ?>"
             class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-md bg-gh-accent px-4 py-2.5 text-sm font-semibold text-white transition-all hover:brightness-110">
            <i class="bi bi-chat-dots-fill"></i> Send message
          </a>
          <a href="profile.php?id=<?php echo $listing['user_id']; ?>"
             class="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-md border border-gh-border bg-gh-panel px-4 py-2.5 text-sm font-semibold transition-colors hover:bg-white/5">
            <i class="bi bi-person-circle"></i> View profile
          </a>
        <?php elseif(!isset($_SESSION['user_id'])): ?>
          <a href="login.php"
             class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-md bg-gh-accent px-4 py-2.5 text-sm font-semibold text-white transition-all hover:brightness-110">
            <i class="bi bi-box-arrow-in-right"></i> Login to contact
          </a>
        <?php endif; ?>
      </div>

      <!-- Safety Tips -->
      <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
        <h3 class="flex items-center gap-2 text-base font-bold">
          <i class="bi bi-shield-check text-gh-success"></i>
          Safety tips
        </h3>
        <ul class="mt-4 space-y-2.5 text-sm text-gh-muted">
          <li class="flex items-start gap-2">
            <i class="bi bi-check-circle-fill mt-0.5 text-gh-success"></i>
            <span>Meet in public places first</span>
          </li>
          <li class="flex items-start gap-2">
            <i class="bi bi-check-circle-fill mt-0.5 text-gh-success"></i>
            <span>Don't share sensitive information</span>
          </li>
          <li class="flex items-start gap-2">
            <i class="bi bi-check-circle-fill mt-0.5 text-gh-success"></i>
            <span>Trust your instincts</span>
          </li>
          <li class="flex items-start gap-2">
            <i class="bi bi-check-circle-fill mt-0.5 text-gh-success"></i>
            <span>Report suspicious behavior</span>
          </li>
        </ul>
        <a href="safety.php" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-gh-accent hover:underline">
          Read all safety tips <i class="bi bi-arrow-right"></i>
        </a>
      </div>

      <!-- Report -->
      <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
        <h3 class="flex items-center gap-2 text-base font-bold text-gh-danger">
          <i class="bi bi-flag-fill"></i>
          Report this listing
        </h3>
        <p class="mt-2 text-sm text-gh-muted">
          If this listing violates our terms or contains inappropriate content, please report it.
        </p>
        <a href="report.php?listing_id=<?php echo $listing_id; ?>"
           class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-md border border-gh-border bg-gh-panel px-4 py-2.5 text-sm font-semibold text-gh-danger transition-colors hover:bg-white/5">
          <i class="bi bi-flag-fill"></i> Report listing
        </a>
      </div>

    </div>
  </div>

</div>

<script>
// Toggle favorite
function toggleFavorite(listingId) {
  const btn = document.getElementById('favoriteBtn');
  const icon = btn.querySelector('i');
  const text = btn.querySelector('span');
  
  fetch('api/favorites.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ listing_id: listingId, action: 'toggle' })
  })
  .then(r => r.json())
  .then(data => {
    if(data.success) {
      if(data.favorited) {
        icon.classList.remove('bi-star');
        icon.classList.add('bi-star-fill');
        btn.classList.add('text-gh-warning');
        btn.classList.remove('text-gh-fg');
        if(text) text.textContent = 'Saved';
      } else {
        icon.classList.remove('bi-star-fill');
        icon.classList.add('bi-star');
        btn.classList.remove('text-gh-warning');
        btn.classList.add('text-gh-fg');
        if(text) text.textContent = 'Save';
      }
    }
  })
  .catch(err => console.error(err));
}

// Share URL
function shareUrl() {
  const url = window.location.href;
  
  if(navigator.share) {
    navigator.share({
      title: '<?php echo addslashes($listing['title']); ?>',
      url: url
    });
  } else if(navigator.clipboard) {
    navigator.clipboard.writeText(url).then(() => {
      alert('Link copied to clipboard!');
    });
  } else {
    prompt('Copy this link:', url);
  }
}
</script>

<?php include 'views/footer.php'; ?>
