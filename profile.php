<?php
session_start();
require_once 'config/database.php';
require_once 'classes/UserProfile.php';
require_once 'classes/MessageLimits.php';

$database = new Database();
$db = $database->getConnection();

$userProfile = new UserProfile($db);
$profile_user_id = $_GET['id'];
$profile_data = $userProfile->getProfile($profile_user_id);

if(!$profile_data) {
    header('Location: choose-location.php');
    exit();
}

$is_own_profile = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile_user_id;

// Check message limits
$can_message = false;
$message_limit_info = null;
if(isset($_SESSION['user_id']) && !$is_own_profile) {
    $messageLimits = new MessageLimits($db);
    $message_limit_info = $messageLimits->canSendMessage($_SESSION['user_id']);
    $can_message = $message_limit_info['can_send'];
}

// Get user's listings
$query = "SELECT l.*, c.name as category_name, ct.name as city_name, s.abbreviation as state_abbr 
          FROM listings l 
          LEFT JOIN categories c ON l.category_id = c.id 
          LEFT JOIN cities ct ON l.city_id = ct.id 
          LEFT JOIN states s ON ct.state_id = s.id 
          WHERE l.user_id = :user_id AND l.status = 'active' 
          ORDER BY l.created_at DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $profile_user_id);
$stmt->execute();
$user_listings = $stmt->fetchAll();

// Get user photos from user_photos table
$query = "SELECT * FROM user_photos WHERE user_id = :user_id ORDER BY is_primary DESC, display_order ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $profile_user_id);
$stmt->execute();
$user_gallery_images = $stmt->fetchAll();

// Get primary photo (avatar) and banner
$primary_photo = null;
$banner_photo = null;
foreach($user_gallery_images as $photo) {
    if($photo['is_primary']) {
        $primary_photo = $photo;
    }
    if(isset($photo['is_banner']) && $photo['is_banner']) {
        $banner_photo = $photo;
    }
}

$avatar_url = $primary_photo ? $primary_photo['file_path'] : ($profile_data['avatar'] ?? '/assets/images/default-avatar.png');
$banner_url = $banner_photo ? $banner_photo['file_path'] : '';

include 'views/header.php';
?>

<div class="mx-auto max-w-6xl px-4 py-8">
  
  <!-- Profile Banner -->
  <div class="relative mb-6 overflow-hidden rounded-xl border border-gh-border bg-gh-panel shadow-lg">
    <?php if($banner_url): ?>
      <div class="h-48 w-full bg-cover bg-center" style="background-image: url('<?php echo htmlspecialchars($banner_url); ?>')"></div>
    <?php else: ?>
      <div class="h-48 w-full bg-gradient-to-br from-gh-panel2 to-gh-accent/20"></div>
    <?php endif; ?>
    
    <div class="relative px-6 pb-6">
      <!-- Avatar -->
      <div class="relative -mt-16 mb-4">
        <img src="<?php echo htmlspecialchars($avatar_url); ?>" 
             alt="<?php echo htmlspecialchars($profile_data['username']); ?>"
             class="h-32 w-32 rounded-full border-4 border-gh-bg object-cover shadow-xl" />
        <?php if($profile_data['is_verified']): ?>
          <div class="absolute bottom-0 right-0 rounded-full border-4 border-gh-bg bg-gh-accent p-1.5">
            <i class="bi bi-check-lg text-white"></i>
          </div>
        <?php endif; ?>
      </div>

      <!-- User Info -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex-1">
          <h1 class="mb-2 text-3xl font-extrabold tracking-tight">
            <?php echo htmlspecialchars($profile_data['username']); ?>
            <?php if($profile_data['is_verified']): ?>
              <i class="bi bi-patch-check-fill ml-2 text-xl text-gh-accent" title="Verified"></i>
            <?php endif; ?>
          </h1>
          
          <div class="mb-3 flex flex-wrap items-center gap-3 text-sm text-gh-muted">
            <?php if(!empty($profile_data['location'])): ?>
              <span class="flex items-center gap-1">
                <i class="bi bi-geo-alt-fill"></i>
                <?php echo htmlspecialchars($profile_data['location']); ?>
              </span>
            <?php endif; ?>
            
            <span class="flex items-center gap-1">
              <i class="bi bi-calendar3"></i>
              Joined <?php echo date('F Y', strtotime($profile_data['created_at'])); ?>
            </span>
            
            <span class="flex items-center gap-1">
              <i class="bi bi-clock"></i>
              <?php echo $profile_data['last_seen'] ? 'Active ' . date('M j', strtotime($profile_data['last_seen'])) : 'Recently active'; ?>
            </span>
          </div>

          <!-- Stats -->
          <div class="flex flex-wrap gap-4">
            <div class="rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2">
              <div class="text-2xl font-bold text-gh-accent"><?php echo count($user_listings); ?></div>
              <div class="text-xs text-gh-muted">Listings</div>
            </div>
            <div class="rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2">
              <div class="text-2xl font-bold text-gh-success"><?php echo $profile_data['rating'] ?? '5.0'; ?></div>
              <div class="text-xs text-gh-muted">Rating</div>
            </div>
            <div class="rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2">
              <div class="text-2xl font-bold"><?php echo $profile_data['total_reviews'] ?? '0'; ?></div>
              <div class="text-xs text-gh-muted">Reviews</div>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap gap-2">
          <?php if($is_own_profile): ?>
            <a href="settings.php" 
               class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2 text-sm font-semibold transition-colors hover:bg-white/5">
              <i class="bi bi-gear-fill"></i>
              Edit Profile
            </a>
            <a href="upload-photos.php" 
               class="inline-flex items-center gap-2 rounded-lg bg-gh-accent px-4 py-2 text-sm font-semibold text-white transition-all hover:brightness-110">
              <i class="bi bi-images"></i>
              Manage Photos
            </a>
          <?php else: ?>
            <?php if($can_message): ?>
              <a href="messages-compose.php?to=<?php echo $profile_user_id; ?>" 
                 class="inline-flex items-center gap-2 rounded-lg bg-gh-accent px-4 py-2 text-sm font-semibold text-white transition-all hover:brightness-110">
                <i class="bi bi-chat-dots-fill"></i>
                Send Message
              </a>
            <?php else: ?>
              <button disabled 
                      class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2 text-sm font-semibold text-gh-muted opacity-50 cursor-not-allowed">
                <i class="bi bi-lock-fill"></i>
                Message Limit Reached
              </button>
            <?php endif; ?>
            
            <button onclick="addFavorite(<?php echo $profile_user_id; ?>)" 
                    class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2 text-sm font-semibold transition-colors hover:bg-white/5">
              <i class="bi bi-heart"></i>
              Favorite
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="grid gap-6 lg:grid-cols-3">
    
    <!-- Left Column -->
    <div class="lg:col-span-1">
      <!-- About -->
      <?php if(!empty($profile_data['bio'])): ?>
        <div class="mb-6 rounded-xl border border-gh-border bg-gh-panel p-6">
          <h3 class="mb-4 flex items-center gap-2 text-lg font-bold">
            <i class="bi bi-person-badge text-gh-accent"></i>
            About Me
          </h3>
          <p class="whitespace-pre-wrap text-sm text-gh-muted leading-relaxed">
            <?php echo nl2br(htmlspecialchars($profile_data['bio'])); ?>
          </p>
        </div>
      <?php endif; ?>

      <!-- Gallery -->
      <?php if(count($user_gallery_images) > 0): ?>
        <div class="mb-6 rounded-xl border border-gh-border bg-gh-panel p-6">
          <h3 class="mb-4 flex items-center gap-2 text-lg font-bold">
            <i class="bi bi-images text-gh-accent"></i>
            Photo Gallery
          </h3>
          <div class="grid grid-cols-3 gap-2">
            <?php foreach($user_gallery_images as $img): ?>
              <div class="group relative aspect-square overflow-hidden rounded-lg border border-gh-border">
                <img src="<?php echo htmlspecialchars($img['file_path']); ?>" 
                     alt="Gallery image"
                     class="h-full w-full cursor-pointer object-cover transition-transform group-hover:scale-110"
                     onclick="openGallery('<?php echo htmlspecialchars($img['file_path']); ?>')" />
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Right Column - Listings -->
    <div class="lg:col-span-2">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="flex items-center gap-2 text-xl font-bold">
          <i class="bi bi-grid-fill text-gh-accent"></i>
          Active Listings
        </h3>
        <?php if($is_own_profile): ?>
          <a href="post-ad.php" 
             class="text-sm font-semibold text-gh-accent hover:underline">
            + Post New
          </a>
        <?php endif; ?>
      </div>

      <?php if(empty($user_listings)): ?>
        <div class="rounded-xl border border-gh-border bg-gh-panel p-12 text-center">
          <i class="bi bi-inbox mb-4 text-5xl text-gh-muted opacity-50"></i>
          <p class="text-gh-muted">No active listings yet</p>
        </div>
      <?php else: ?>
        <div class="grid gap-4 sm:grid-cols-2">
          <?php foreach($user_listings as $listing): ?>
            <a href="listing.php?id=<?php echo $listing['id']; ?>" 
               class="group rounded-xl border border-gh-border bg-gh-panel transition-colors hover:bg-gh-panel2">
              <?php if($listing['image_url']): ?>
                <div class="aspect-video overflow-hidden rounded-t-xl bg-gh-panel2">
                  <img src="<?php echo htmlspecialchars($listing['image_url']); ?>" 
                       alt="<?php echo htmlspecialchars($listing['title']); ?>"
                       class="h-full w-full object-cover transition-transform group-hover:scale-105" />
                </div>
              <?php endif; ?>
              <div class="p-4">
                <h4 class="mb-2 font-bold text-gh-fg group-hover:text-gh-accent line-clamp-2">
                  <?php echo htmlspecialchars($listing['title']); ?>
                </h4>
                <div class="mb-2 flex items-center gap-2 text-xs text-gh-muted">
                  <span class="rounded-full border border-gh-border bg-gh-panel2 px-2 py-0.5">
                    <?php echo htmlspecialchars($listing['category_name']); ?>
                  </span>
                  <span>•</span>
                  <span><?php echo htmlspecialchars($listing['city_name']); ?>, <?php echo $listing['state_abbr']; ?></span>
                </div>
                <p class="text-sm text-gh-muted line-clamp-2">
                  <?php echo htmlspecialchars($listing['description']); ?>
                </p>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function openGallery(imagePath) {
  // Simple lightbox
  const overlay = document.createElement('div');
  overlay.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4';
  overlay.innerHTML = `
    <img src="${imagePath}" class="max-h-full max-w-full rounded-lg" />
    <button class="absolute top-4 right-4 rounded-lg bg-white/10 p-3 text-white hover:bg-white/20">
      <i class="bi bi-x-lg text-2xl"></i>
    </button>
  `;
  overlay.onclick = () => overlay.remove();
  document.body.appendChild(overlay);
}

function addFavorite(userId) {
  fetch('add-favorite.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `user_id=${userId}`
  })
  .then(r => r.json())
  .then(data => {
    alert(data.message);
  });
}
</script>

<?php include 'views/footer.php'; ?>
