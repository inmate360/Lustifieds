<?php
session_start();
require_once 'config/database.php';
require_once 'classes/LocationService.php';
require_once 'classes/CSRF.php';
require_once 'includes/maintenance_check.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

$locationService = new LocationService($db);

$error = '';
$success = '';

// Get user's listings
$query = "SELECT id, title FROM listings WHERE user_id = :user_id AND status = 'active' ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user_listings = $stmt->fetchAll();

// Get all states with cities
$query = "SELECT s.id as state_id, s.name as state_name, s.abbreviation,
          c.id as city_id, c.name as city_name
          FROM states s
          LEFT JOIN cities c ON s.id = c.state_id
          ORDER BY s.name ASC, c.name ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$locations = $stmt->fetchAll();

// Organize by state
$states_cities = [];
foreach($locations as $loc) {
    if(!isset($states_cities[$loc['state_id']])) {
        $states_cities[$loc['state_id']] = [
            'name' => $loc['state_name'],
            'abbreviation' => $loc['abbreviation'],
            'cities' => []
        ];
    }
    if($loc['city_id']) {
        $states_cities[$loc['state_id']]['cities'][] = [
            'id' => $loc['city_id'],
            'name' => $loc['city_name']
        ];
    }
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        $error = 'Invalid security token';
    } else {
        $listing_id = (int)$_POST['listing_id'];
        $city_ids = $_POST['cities'] ?? [];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        
        if(empty($listing_id) || empty($city_ids) || empty($start_date) || empty($end_date)) {
            $error = 'All fields are required';
        } elseif(strtotime($start_date) < time()) {
            $error = 'Start date must be in the future';
        } elseif(strtotime($end_date) <= strtotime($start_date)) {
            $error = 'End date must be after start date';
        } elseif(count($city_ids) > 10) {
            $error = 'Maximum 10 cities allowed';
        } else {
            $result = $locationService->createTravelListing($listing_id, $city_ids, $start_date, $end_date);
            
            if($result['success']) {
                CSRF::destroyToken();
                $_SESSION['success'] = 'Travel listing created successfully!';
                header('Location: my-listings.php');
                exit();
            } else {
                $error = $result['error'] ?? 'Failed to create travel listing';
            }
        }
    }
}

$csrf_token = CSRF::getToken();

include 'views/header.php';
?>

<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">

<style>
.travel-header {
    background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
    text-align: center;
}

.travel-form {
    max-width: 800px;
    margin: 0 auto;
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 2rem;
}

.cities-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    max-height: 400px;
    overflow-y: auto;
    padding: 1rem;
    background: rgba(66, 103, 245, 0.05);
    border-radius: 10px;
}

.city-checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.city-checkbox:hover {
    border-color: var(--primary-blue);
    background: rgba(66, 103, 245, 0.1);
}

.city-checkbox input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.city-checkbox.selected {
    border-color: var(--primary-blue);
    background: rgba(66, 103, 245, 0.2);
}

.selected-cities {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1rem;
    padding: 1rem;
    background: rgba(66, 103, 245, 0.05);
    border-radius: 10px;
    min-height: 60px;
}

.selected-city-tag {
    background: var(--primary-blue);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.selected-city-tag .remove {
    cursor: pointer;
    font-weight: bold;
    opacity: 0.8;
}

.selected-city-tag .remove:hover {
    opacity: 1;
}

.date-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.info-box {
    background: rgba(66, 103, 245, 0.1);
    border: 2px solid var(--primary-blue);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.search-cities {
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .date-inputs {
        grid-template-columns: 1fr;
    }
    
    .cities-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-content">
    <div class="container">
        <div class="travel-header">
            <div style="font-size: 3rem; margin-bottom: 0.5rem;">✈️</div>
            <h1 style="margin-bottom: 0.5rem;">Travel Mode</h1>
            <p style="opacity: 0.9;">Post your listing in multiple cities you'll be visiting</p>
        </div>

        <div class="travel-form">
            <?php if($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="info-box">
                <h3 style="color: var(--primary-blue); margin-bottom: 1rem;">💡 How Travel Mode Works</h3>
                <ul style="color: var(--text-gray); line-height: 2; margin-left: 1.5rem;">
                    <li>Select an existing listing to promote in other cities</li>
                    <li>Choose up to 10 cities you'll be visiting</li>
                    <li>Set your travel dates (start and end)</li>
                    <li>Your listing will appear in all selected cities during your visit</li>
                    <li>Perfect for business trips, vacations, or relocations</li>
                </ul>
            </div>

            <?php if(count($user_listings) == 0): ?>
            <div class="alert alert-warning">
                <strong>No Active Listings</strong><br>
                You need to create a listing first before using travel mode.
                <a href="create-listing.php" style="color: var(--primary-blue); text-decoration: underline;">
                    Create a listing now
                </a>
            </div>
            <?php else: ?>

            <form method="POST" action="">
                <?php echo CSRF::getHiddenInput(); ?>

                <div class="form-group">
                    <label>Select Listing to Promote</label>
                    <select name="listing_id" required>
                        <option value="">Choose a listing...</option>
                        <?php foreach($user_listings as $listing): ?>
                        <option value="<?php echo $listing['id']; ?>">
                            <?php echo htmlspecialchars($listing['title']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Travel Dates</label>
                    <div class="date-inputs">
                        <div>
                            <label style="font-size: 0.9rem; color: var(--text-gray);">Start Date</label>
                            <input type="date" name="start_date" required 
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div>
                            <label style="font-size: 0.9rem; color: var(--text-gray);">End Date</label>
                            <input type="date" name="end_date" required 
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Select Cities (up to 10)</label>
                    <div class="search-cities">
                        <input type="text" 
                               id="citySearch" 
                               placeholder="🔍 Search cities..." 
                               style="width: 100%;"
                               onkeyup="filterCities(this.value)">
                    </div>
                    
                    <div class="selected-cities" id="selectedCitiesDisplay">
                        <span style="color: var(--text-gray);">No cities selected</span>
                    </div>

                    <div class="cities-grid" id="citiesGrid">
                        <?php foreach($states_cities as $state_id => $state): ?>
                            <div style="grid-column: 1 / -1; margin-top: 1rem;">
                                <strong style="color: var(--primary-blue);">
                                    <?php echo htmlspecialchars($state['name']); ?> (<?php echo $state['abbreviation']; ?>)
                                </strong>
                            </div>
                            <?php foreach($state['cities'] as $city): ?>
                            <label class="city-checkbox" data-city-name="<?php echo strtolower($city['name']); ?>">
                                <input type="checkbox" 
                                       name="cities[]" 
                                       value="<?php echo $city['id']; ?>"
                                       onchange="updateSelectedCities()">
                                <span><?php echo htmlspecialchars($city['name']); ?></span>
                            </label>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="alert alert-info" style="margin-top: 2rem;">
                    <strong>💰 Travel Mode Pricing</strong><br>
                    Travel mode is a premium feature. Your listing will be featured in all selected cities during your travel dates.
                </div>

                <button type="submit" class="btn-primary btn-block" style="margin-top: 2rem;">
                    ✈️ Activate Travel Mode
                </button>
            </form>

            <?php endif; ?>
        </div>
    </div>
</div>

<script>
let selectedCities = [];
const maxCities = 10;

function updateSelectedCities() {
    const checkboxes = document.querySelectorAll('.city-checkbox input[type="checkbox"]');
    const display = document.getElementById('selectedCitiesDisplay');
    
    selectedCities = [];
    
    checkboxes.forEach(checkbox => {
        const label = checkbox.closest('.city-checkbox');
        
        if(checkbox.checked) {
            selectedCities.push({
                id: checkbox.value,
                name: label.querySelector('span').textContent
            });
            label.classList.add('selected');
        } else {
            label.classList.remove('selected');
        }
    });
    
    // Limit to max cities
    if(selectedCities.length > maxCities) {
        alert(`Maximum ${maxCities} cities allowed`);
        checkboxes[checkboxes.length - 1].checked = false;
        updateSelectedCities();
        return;
    }
    
    // Update display
    if(selectedCities.length === 0) {
        display.innerHTML = '<span style="color: var(--text-gray);">No cities selected</span>';
    } else {
        display.innerHTML = selectedCities.map(city => `
            <div class="selected-city-tag">
                <span>${city.name}</span>
                <span class="remove" onclick="removeCity(${city.id})">✕</span>
            </div>
        `).join('');
    }
}

function removeCity(cityId) {
    const checkbox = document.querySelector(`input[value="${cityId}"]`);
    if(checkbox) {
        checkbox.checked = false;
        updateSelectedCities();
    }
}

function filterCities(search) {
    const searchTerm = search.toLowerCase();
    const cityCheckboxes = document.querySelectorAll('.city-checkbox');
    
    cityCheckboxes.forEach(checkbox => {
        const cityName = checkbox.getAttribute('data-city-name');
        if(cityName && cityName.includes(searchTerm)) {
            checkbox.style.display = 'flex';
        } else if(cityName) {
            checkbox.style.display = 'none';
        }
    });
}
</script>

<?php include 'views/footer.php'; ?>