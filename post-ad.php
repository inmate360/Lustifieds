<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Listing.php';
require_once 'includes/profile_required.php';
require_once 'classes/CSRF.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Require complete profile
requireCompleteProfile($db, $_SESSION['user_id']);

$success = '';
$error = '';

// Get categories
$query = "SELECT * FROM categories ORDER BY name ASC";
$stmt = $db->query($query);
$categories = $stmt->fetchAll();

// Get cities
$query = "SELECT * FROM cities ORDER BY name ASC LIMIT 100";
$stmt = $db->query($query);
$cities = $stmt->fetchAll();

// Check daily limit
$query = "SELECT COUNT(*) FROM listings WHERE user_id = :user_id AND DATE(created_at) = CURDATE()";
$stmt = $db->prepare($query);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$today_count = $stmt->fetchColumn();

// Check if user is premium
$query = "SELECT is_premium FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$is_premium = $stmt->fetchColumn();

$daily_limit = $is_premium ? 999 : 3;
$can_post = $today_count < $daily_limit;

if($_SERVER['REQUEST_METHOD'] === 'POST' && $can_post) {
    if(!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $category_id = (int)$_POST['category_id'];
        $city_id = (int)$_POST['city_id'];
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $contact_method = $_POST['contact_method'] ?? 'message';
        
        if(empty($title) || empty($description)) {
            $error = 'Please fill in all required fields';
        } elseif(strlen($title) < 10) {
            $error = 'Title must be at least 10 characters';
        } elseif(strlen($description) < 50) {
            $error = 'Description must be at least 50 characters';
        } else {
            try {
                $listing = new Listing($db);
                
                // Handle photo upload
                $photo_url = null;
                if(isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/listings/';
                    if(!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if(in_array($file_ext, $allowed_exts)) {
                        $file_name = uniqid() . '_' . time() . '.' . $file_ext;
                        $file_path = $upload_dir . $file_name;
                        
                        if(move_uploaded_file($_FILES['photo']['tmp_name'], $file_path)) {
                            $photo_url = '/' . $file_path;
                        }
                    }
                }
                
                $result = $listing->create(
                    $_SESSION['user_id'],
                    $category_id,
                    $city_id,
                    $title,
                    $description,
                    $photo_url,
                    $contact_method
                );
                
                if($result) {
                    header('Location: my-listings.php?success=1');
                    exit();
                } else {
                    $error = 'Failed to create listing';
                }
            } catch(Exception $e) {
                $error = 'An error occurred: ' . $e->getMessage();
            }
        }
    }
}

include 'views/header.php';
?>

<div class="mx-auto max-w-4xl px-4 py-8">
  
  <!-- Page Header -->
  <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
    <div class="flex items-center gap-3">
      <div class="flex h-12 w-12 items-center justify-center rounded-lg border border-gh-border bg-gh-panel2">
        <i class="bi bi-plus-circle-fill text-2xl text-gh-accent"></i>
      </div>
      <div>
        <h1 class="text-2xl font-extrabold tracking-tight">Create new listing</h1>
        <p class="mt-1 text-sm text-gh-muted">Share your ad with the community</p>
      </div>
    </div>
  </div>

  <!-- Alerts -->
  <?php if(!empty($success)): ?>
    <div class="mt-4 rounded-xl border border-gh-border bg-gh-panel p-4">
      <div class="flex items-start gap-3">
        <i class="bi bi-check-circle-fill text-xl text-gh-success"></i>
        <div class="flex-1 text-sm">
          <span class="font-semibold text-gh-success">Success!</span>
          <span class="text-gh-fg"> <?php echo htmlspecialchars($success); ?></span>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if(!empty($error)): ?>
    <div class="mt-4 rounded-xl border border-gh-border bg-gh-panel p-4">
      <div class="flex items-start gap-3">
        <i class="bi bi-exclamation-triangle-fill text-xl text-gh-danger"></i>
        <div class="flex-1 text-sm">
          <span class="font-semibold text-gh-danger">Error:</span>
          <span class="text-gh-fg"> <?php echo htmlspecialchars($error); ?></span>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Daily Limit Info -->
  <div class="mt-4 rounded-xl border border-gh-border bg-gh-panel p-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-start gap-3">
        <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-gh-border bg-gh-panel2">
          <i class="bi bi-clipboard-check text-lg text-gh-accent"></i>
        </div>
        <div>
          <div class="text-sm font-bold">Daily posting limit</div>
          <div class="mt-1 text-sm text-gh-muted">
            You've posted <span class="font-semibold text-gh-fg"><?php echo $today_count; ?></span> of 
            <span class="font-semibold text-gh-fg"><?php echo $daily_limit; ?></span> listings today.
            <?php if(!$is_premium && $today_count >= 2): ?>
              <a class="ml-1 font-semibold text-gh-accent hover:underline" href="subscription-bitcoin.php">Upgrade to Premium</a> for unlimited posts.
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <?php if($is_premium): ?>
        <span class="inline-flex items-center gap-2 rounded-full border border-gh-border bg-gh-panel2 px-3 py-1.5 text-xs font-bold text-gh-warning">
          <i class="bi bi-gem"></i> Premium
        </span>
      <?php endif; ?>
    </div>
  </div>

  <?php if(!$can_post): ?>
    <!-- Daily Limit Reached -->
    <div class="mt-4 rounded-xl border border-gh-border bg-gh-panel p-6 text-center">
      <div class="text-4xl opacity-20">🚫</div>
      <div class="mt-4 text-base font-bold">Daily limit reached</div>
      <div class="mt-2 text-sm text-gh-muted">
        You can post again tomorrow, or 
        <a class="font-semibold text-gh-accent hover:underline" href="subscription-bitcoin.php">upgrade to Premium</a> 
        for unlimited posts.
      </div>
      <div class="mt-6">
        <a href="subscription-bitcoin.php" class="inline-flex items-center gap-2 rounded-md bg-gh-accent px-5 py-2.5 text-sm font-semibold text-white transition-all hover:brightness-110">
          <i class="bi bi-gem"></i> Upgrade now
        </a>
      </div>
    </div>
  <?php else: ?>

    <!-- Post Form -->
    <form method="POST" enctype="multipart/form-data" id="postForm" class="mt-6 space-y-6">
      <?php echo CSRF::getHiddenInput(); ?>

      <!-- Category Selection -->
      <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
        <h2 class="flex items-center gap-2 text-base font-bold">
          <i class="bi bi-grid-3x3-gap-fill text-gh-accent"></i>
          Select category
        </h2>
        <p class="mt-1 text-sm text-gh-muted">Choose the category that best fits your listing</p>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
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
          ?>
            <label class="group relative cursor-pointer">
              <input type="radio" 
                     name="category_id" 
                     value="<?php echo $cat['id']; ?>" 
                     class="peer absolute opacity-0" 
                     required>
              <div class="rounded-lg border-2 border-gh-border bg-gh-panel2 p-4 text-center transition-all peer-checked:border-gh-accent peer-checked:bg-gh-accent/10 group-hover:bg-white/5">
                <div class="text-3xl"><?php echo $icons[$cat['slug']] ?? '📋'; ?></div>
                <div class="mt-2 text-sm font-semibold"><?php echo htmlspecialchars($cat['name']); ?></div>
              </div>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Location & Contact -->
      <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
        <h2 class="flex items-center gap-2 text-base font-bold">
          <i class="bi bi-geo-alt-fill text-gh-accent"></i>
          Location & contact
        </h2>
        
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-2 block text-sm font-semibold">
              City <span class="text-gh-danger">*</span>
            </label>
            <select name="city_id" 
                    required
                    class="w-full rounded-md border border-gh-border bg-gh-panel2 px-3 py-2.5 text-gh-fg transition-colors focus:outline-none focus:ring-2 focus:ring-gh-accent">
              <option value="">Select a city...</option>
              <?php foreach($cities as $c): ?>
                <option value="<?php echo $c['id']; ?>" 
                        <?php echo (isset($_SESSION['current_city']) && $_SESSION['current_city'] == $c['name']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($c['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="mt-2 text-xs text-gh-muted">
              <i class="bi bi-info-circle mr-1"></i>Your listing will appear in this city
            </p>
          </div>

          <div>
            <label class="mb-2 block text-sm font-semibold">Contact method</label>
            <select name="contact_method"
                    class="w-full rounded-md border border-gh-border bg-gh-panel2 px-3 py-2.5 text-gh-fg transition-colors focus:outline-none focus:ring-2 focus:ring-gh-accent">
              <option value="message" selected>Site messages</option>
              <option value="both">Email + messages</option>
            </select>
            <p class="mt-2 text-xs text-gh-muted">
              <i class="bi bi-info-circle mr-1"></i>How others can contact you
            </p>
          </div>
        </div>
      </div>

      <!-- Listing Details -->
      <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
        <h2 class="flex items-center gap-2 text-base font-bold">
          <i class="bi bi-file-text-fill text-gh-accent"></i>
          Listing details
        </h2>

        <div class="mt-4 space-y-4">
          <div>
            <label class="mb-2 block text-sm font-semibold">
              Title <span class="text-gh-danger">*</span>
            </label>
            <input type="text" 
                   name="title" 
                   id="titleInput"
                   maxlength="200" 
                   required
                   placeholder="Enter a catchy title for your listing..."
                   class="w-full rounded-md border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-colors focus:outline-none focus:ring-2 focus:ring-gh-accent" />
            <div class="mt-2 flex items-center justify-between text-xs text-gh-muted">
              <span>Make it descriptive and attention-grabbing</span>
              <span><span id="titleCount">0</span> / 200</span>
            </div>
          </div>

          <div>
            <label class="mb-2 block text-sm font-semibold">
              Description <span class="text-gh-danger">*</span>
            </label>
            <textarea name="description" 
                      id="descInput"
                      required
                      placeholder="Describe what you're looking for in detail..."
                      class="min-h-[200px] w-full rounded-md border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-colors focus:outline-none focus:ring-2 focus:ring-gh-accent"></textarea>
            <div class="mt-2 flex items-center justify-between text-xs">
              <span class="text-gh-muted">Minimum 50 characters</span>
              <span id="descCount" class="font-semibold text-gh-muted">0 chars</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Photo Upload -->
      <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
        <h2 class="flex items-center gap-2 text-base font-bold">
          <i class="bi bi-image-fill text-gh-accent"></i>
          Add photo <span class="ml-2 text-xs font-normal text-gh-muted">(optional)</span>
        </h2>
        <p class="mt-1 text-sm text-gh-muted">Listings with photos get 3x more views</p>

        <div class="mt-4">
          <input type="file" 
                 name="photo" 
                 id="photoInput" 
                 accept="image/*" 
                 class="hidden" />
          
          <div id="uploadArea" 
               onclick="document.getElementById('photoInput').click()"
               class="cursor-pointer rounded-lg border-2 border-dashed border-gh-border bg-gh-panel2 p-8 text-center transition-colors hover:border-gh-accent hover:bg-white/5">
            <i class="bi bi-cloud-upload text-5xl text-gh-muted"></i>
            <div class="mt-3 font-semibold">Click to upload</div>
            <div class="mt-1 text-sm text-gh-muted">JPG, PNG or GIF (max 5MB)</div>
          </div>

          <img id="imagePreview" 
               class="mt-4 hidden max-h-[400px] w-full rounded-lg border border-gh-border object-cover" />
        </div>
      </div>

      <!-- Submit -->
      <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
        <a href="my-listings.php" 
           class="inline-flex items-center justify-center gap-2 rounded-md border border-gh-border bg-gh-panel px-5 py-2.5 text-sm font-semibold transition-colors hover:bg-white/5">
          <i class="bi bi-x-circle"></i> Cancel
        </a>
        <button type="submit" 
                id="submitBtn"
                class="inline-flex items-center justify-center gap-2 rounded-md bg-gh-accent px-6 py-2.5 text-sm font-semibold text-white transition-all hover:brightness-110">
          <i class="bi bi-send-fill"></i> Post listing
        </button>
      </div>
    </form>

  <?php endif; ?>
</div>

<script>
// Character counters
const titleInput = document.getElementById('titleInput');
const descInput = document.getElementById('descInput');
const titleCount = document.getElementById('titleCount');
const descCount = document.getElementById('descCount');

if(titleInput && titleCount) {
  titleInput.addEventListener('input', () => {
    titleCount.textContent = titleInput.value.length;
  });
}

if(descInput && descCount) {
  descInput.addEventListener('input', () => {
    const count = descInput.value.length;
    descCount.textContent = count + ' chars';
    
    if(count >= 50) {
      descCount.classList.remove('text-gh-danger');
      descCount.classList.add('text-gh-success');
    } else {
      descCount.classList.remove('text-gh-success');
      descCount.classList.add('text-gh-danger');
    }
  });
}

// Image preview
const photoInput = document.getElementById('photoInput');
const preview = document.getElementById('imagePreview');
const uploadArea = document.getElementById('uploadArea');

if(photoInput && preview) {
  photoInput.addEventListener('change', (e) => {
    const file = e.target.files && e.target.files[0];
    if(!file) return;
    
    // Check file size (5MB)
    if(file.size > 5 * 1024 * 1024) {
      alert('File size must be less than 5MB');
      photoInput.value = '';
      return;
    }
    
    const reader = new FileReader();
    reader.onload = (ev) => {
      preview.src = ev.target.result;
      preview.classList.remove('hidden');
      uploadArea.classList.add('hidden');
    };
    reader.readAsDataURL(file);
  });
}

// Form submission
const form = document.getElementById('postForm');
const submitBtn = document.getElementById('submitBtn');

if(form && submitBtn) {
  form.addEventListener('submit', () => {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span> Posting...';
  });
}
</script>

<?php include 'views/footer.php'; ?>
