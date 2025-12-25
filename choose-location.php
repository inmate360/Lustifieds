<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Location.php';
require_once 'includes/maintenance_check.php';

$database = new Database();
$db = $database->getConnection();

// Check for maintenance mode
if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

$location = new Location($db);

// Get all states
$states = $location->getAllStates();
$total_locations = $location->getTotalLocationsCount();

// Get popular cities
$popular_cities = [];
try {
    $query = "SELECT c.*, s.name as state_name, s.abbreviation as state_abbr,
              (SELECT COUNT(*) FROM listings WHERE city_id = c.id AND status = 'active') as listing_count
              FROM cities c
              LEFT JOIN states s ON c.state_id = s.id
              ORDER BY listing_count DESC, c.name ASC
              LIMIT 20";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $popular_cities = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error fetching popular cities: " . $e->getMessage());
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['state']) && isset($_POST['city'])) {
    $_SESSION['selected_state'] = $_POST['state'];
    $_SESSION['selected_city'] = $_POST['city'];
    header('Location: city.php?location=' . $_POST['city']);
    exit();
}

include 'views/header.php';
?>

<div class="min-h-[70vh] py-10">
  <div class="mx-auto max-w-6xl px-4">
    
    <!-- Brand Header -->
    <div class="mb-10 text-center">
      <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl border-2 border-gh-border bg-gradient-to-br from-gh-panel to-gh-panel2 text-2xl font-black shadow-lg">
        <span class="bg-gradient-to-br from-gh-accent to-gh-success bg-clip-text text-transparent">TP</span>
      </div>
      <h1 class="bg-gradient-to-r from-gh-fg to-gh-muted bg-clip-text text-4xl font-extrabold tracking-tight text-transparent">
        Turnpage
      </h1>
      <p class="mt-3 text-lg text-gh-muted">Local hookup classifieds</p>
    </div>

    <!-- Main Grid -->
    <div class="grid gap-6 lg:grid-cols-5">
      
      <!-- Location Chooser (3/5) -->
      <div class="lg:col-span-3">
        <div class="rounded-xl border border-gh-border bg-gh-panel shadow-sm">
          
          <!-- Header -->
          <div class="border-b border-gh-border p-6">
            <h2 class="text-xl font-bold">Choose your city</h2>
            <p class="mt-1 text-sm text-gh-muted">Browse personal ads in United States</p>
          </div>

          <!-- Stats Banner -->
          <div class="border-b border-gh-border bg-gh-panel2 px-6 py-4">
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-gh-border bg-gh-panel">
                <i class="bi bi-geo-alt-fill text-lg text-gh-accent"></i>
              </div>
              <div>
                <div class="text-sm font-semibold text-gh-fg">
                  Coverage: <span class="text-gh-accent"><?php echo number_format($total_locations); ?></span> locations
                </div>
                <div class="text-xs text-gh-muted">Nationwide personal ads platform 🎉</div>
              </div>
            </div>
          </div>

          <!-- Form -->
          <form method="POST" action="choose-location.php" id="locationForm" class="p-6 space-y-5">
            
            <!-- State Select -->
            <div>
              <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-gh-fg">
                <i class="bi bi-map-fill text-gh-accent"></i>
                Select state
              </label>
              <select name="state" id="stateSelect" required
                      class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-3 text-gh-fg shadow-sm transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50">
                <option value="">Choose a state...</option>
                <?php foreach($states as $state): ?>
                  <option value="<?php echo $state['id']; ?>" data-abbr="<?php echo $state['abbreviation']; ?>">
                    <?php echo htmlspecialchars($state['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="mt-2 text-xs text-gh-muted">
                <i class="bi bi-info-circle mr-1"></i>Start by selecting your state
              </p>
            </div>

            <!-- City Select -->
            <div>
              <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-gh-fg">
                <i class="bi bi-building text-gh-accent"></i>
                Select city
              </label>
              <select name="city" id="citySelect" required disabled
                      class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-3 text-gh-fg shadow-sm transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50 disabled:cursor-not-allowed disabled:opacity-50">
                <option value="">Select state first...</option>
              </select>
              <p class="mt-2 text-xs text-gh-muted">
                <i class="bi bi-info-circle mr-1"></i>Your city will appear after selecting a state
              </p>
            </div>

            <!-- Submit Button -->
            <button type="submit" 
                    class="group relative w-full overflow-hidden rounded-lg bg-gh-accent px-4 py-3 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110 hover:shadow-xl disabled:cursor-not-allowed disabled:opacity-50"
                    id="continueBtn" disabled>
              <span class="relative z-10 flex items-center justify-center gap-2">
                <i class="bi bi-arrow-right-circle-fill text-base"></i>
                Continue to classifieds
              </span>
              <div class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/20 to-transparent transition-transform group-hover:translate-x-full"></div>
            </button>

            <!-- Terms -->
            <p class="text-center text-xs text-gh-muted">
              By continuing, you agree to the 
              <a href="terms.php" class="text-gh-accent hover:underline">community guidelines</a> 
              and 
              <a href="safety.php" class="text-gh-accent hover:underline">safety rules</a>
            </p>
          </form>
        </div>
      </div>

      <!-- Popular Cities Sidebar (2/5) -->
      <div class="lg:col-span-2">
        <div class="rounded-xl border border-gh-border bg-gh-panel shadow-sm">
          
          <!-- Header -->
          <div class="border-b border-gh-border p-6">
            <div class="flex items-center gap-2">
              <i class="bi bi-fire text-lg text-gh-accent"></i>
              <h3 class="text-base font-bold">Popular cities</h3>
            </div>
            <p class="mt-1 text-sm text-gh-muted">Jump in where listings are active</p>
          </div>

          <!-- Cities List -->
          <?php if(count($popular_cities) > 0): ?>
            <div class="divide-y divide-gh-border">
              <?php foreach(array_slice($popular_cities, 0, 12) as $city): ?>
                <a href="city.php?location=<?php echo htmlspecialchars($city['slug']); ?>"
                   class="group flex items-center justify-between gap-3 px-6 py-4 transition-colors hover:bg-gh-panel2">
                  <div class="min-w-0 flex-1">
                    <div class="truncate font-semibold leading-tight group-hover:text-gh-accent">
                      <?php echo htmlspecialchars($city['name']); ?>
                    </div>
                    <div class="mt-1 flex items-center gap-2 text-xs text-gh-muted">
                      <span class="inline-flex items-center gap-1">
                        <i class="bi bi-geo-alt-fill"></i>
                        <?php echo htmlspecialchars($city['state_abbr']); ?>
                      </span>
                      <?php if($city['listing_count'] > 0): ?>
                        <span>•</span>
                        <span class="text-gh-accent"><?php echo number_format($city['listing_count']); ?> ads</span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <i class="bi bi-arrow-right text-gh-muted transition-transform group-hover:translate-x-1 group-hover:text-gh-accent"></i>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="p-6 text-center text-sm text-gh-muted">
              <i class="bi bi-inbox text-3xl opacity-20"></i>
              <div class="mt-2">No popular cities yet</div>
            </div>
          <?php endif; ?>

          <!-- Footer Links -->
          <div class="border-t border-gh-border bg-gh-panel2 px-6 py-4">
            <div class="text-xs text-gh-muted">
              <div class="mb-2 font-semibold">Currently set for United States</div>
              <div class="flex flex-wrap gap-x-3 gap-y-1">
                <a class="transition-colors hover:text-gh-fg hover:underline" href="terms.php">Terms</a>
                <a class="transition-colors hover:text-gh-fg hover:underline" href="privacy.php">Privacy</a>
                <a class="transition-colors hover:text-gh-fg hover:underline" href="safety.php">Safety</a>
                <a class="transition-colors hover:text-gh-fg hover:underline" href="support.php">Support</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Feature Cards -->
    <div class="mt-8 grid gap-4 sm:grid-cols-3">
      <div class="rounded-xl border border-gh-border bg-gh-panel p-5 text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-lg border border-gh-border bg-gh-panel2">
          <i class="bi bi-shield-check text-2xl text-gh-success"></i>
        </div>
        <h3 class="mt-3 font-bold">Safe & secure</h3>
        <p class="mt-1 text-sm text-gh-muted">Your privacy is our priority</p>
      </div>

      <div class="rounded-xl border border-gh-border bg-gh-panel p-5 text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-lg border border-gh-border bg-gh-panel2">
          <i class="bi bi-people-fill text-2xl text-gh-accent"></i>
        </div>
        <h3 class="mt-3 font-bold">Active community</h3>
        <p class="mt-1 text-sm text-gh-muted">Thousands of members online</p>
      </div>

      <div class="rounded-xl border border-gh-border bg-gh-panel p-5 text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-lg border border-gh-border bg-gh-panel2">
          <i class="bi bi-lightning-charge-fill text-2xl text-gh-warning"></i>
        </div>
        <h3 class="mt-3 font-bold">Quick & easy</h3>
        <p class="mt-1 text-sm text-gh-muted">Post ads in 2 minutes</p>
      </div>
    </div>

  </div>
</div>

<script>
// State selection handler
document.getElementById('stateSelect').addEventListener('change', function() {
  const stateId = this.value;
  const citySelect = document.getElementById('citySelect');
  const continueBtn = document.getElementById('continueBtn');
  
  if(!stateId) {
    citySelect.disabled = true;
    citySelect.innerHTML = '<option value="">Select state first...</option>';
    continueBtn.disabled = true;
    return;
  }
  
  // Disable and show loading
  citySelect.disabled = true;
  citySelect.innerHTML = '<option value="">Loading cities...</option>';
  
  // Fetch cities
  fetch('get-cities.php?state_id=' + stateId)
    .then(response => response.json())
    .then(cities => {
      citySelect.innerHTML = '<option value="">Choose a city...</option>';
      
      cities.forEach(city => {
        const option = document.createElement('option');
        option.value = city.slug;
        option.textContent = city.name;
        citySelect.appendChild(option);
      });
      
      citySelect.disabled = false;
    })
    .catch(error => {
      console.error('Error fetching cities:', error);
      citySelect.innerHTML = '<option value="">Error loading cities</option>';
    });
});

// City selection handler
document.getElementById('citySelect').addEventListener('change', function() {
  const continueBtn = document.getElementById('continueBtn');
  continueBtn.disabled = !this.value;
});

// Add loading state to form submission
document.getElementById('locationForm').addEventListener('submit', function() {
  const btn = document.getElementById('continueBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="inline-flex items-center gap-2"><span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span> Loading...</span>';
});
</script>

<?php include 'views/footer.php'; ?>
