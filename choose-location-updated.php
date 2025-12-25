<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Location.php';
require_once 'classes/CityPersistence.php';
require_once 'includes/maintenance_check.php';

$database = new Database();
$db = $database->getConnection();

// Check for maintenance mode
if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

$location = new Location($db);

// Check if user has saved city and isn't forcing change
if(isset($_SESSION['user_id']) && !isset($_GET['change'])) {
    $cityPersistence = new CityPersistence($db);
    $saved_city = $cityPersistence->getSavedCity($_SESSION['user_id']);
    
    if($saved_city) {
        // Redirect to saved city
        header('Location: city.php?location=' . $saved_city['city_slug']);
        exit();
    }
}

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
    $state_id = (int)$_POST['state'];
    $city_slug = $_POST['city'];
    
    // Get city details
    $query = "SELECT id, slug FROM cities WHERE slug = :slug AND state_id = :state_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':slug', $city_slug);
    $stmt->bindParam(':state_id', $state_id);
    $stmt->execute();
    $city = $stmt->fetch();
    
    if($city && isset($_SESSION['user_id'])) {
        // Save to database for logged-in users
        $cityPersistence = new CityPersistence($db);
        $cityPersistence->saveCity($_SESSION['user_id'], $city['id'], $city_slug, $state_id);
    }
    
    // Also save to session
    $_SESSION['selected_state'] = $state_id;
    $_SESSION['selected_city'] = $city_slug;
    $_SESSION['current_city'] = $city_slug;
    
    header('Location: city.php?location=' . $city_slug);
    exit();
}

include 'views/header.php';
?>

<!-- Rest of your existing choose-location.php HTML stays the same -->