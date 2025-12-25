<?php
session_start();
require_once 'config/database.php';
require_once 'classes/LocationService.php';
require_once 'includes/maintenance_check.php';

$database = new Database();
$db = $database->getConnection();

if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

$locationService = new LocationService($db);

// Get city from URL
$city_slug = $_GET['city'] ?? '';
$neighborhood = $_GET['neighborhood'] ?? '';

// Get city info
$query = "SELECT c.*, s.name as state_name, s.abbreviation as state_abbr
          FROM cities c
          LEFT JOIN states s ON c.state_id = s.id
          WHERE c.slug = :slug
          LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':slug', $city_slug);
$stmt->execute();
$city = $stmt->fetch();

if(!$city) {
    header('Location: choose-location.php');
    exit();
}

// Get neighborhoods for this city
$neighborhoods_result = $locationService->getNeighborhoods($city['id']);
$neighborhoods = $neighborhoods_result['success'] ? $neighborhoods_result['neighborhoods'] : [];

// Get listings by neighborhood
$listings = [];
if($neighborhood) {
    $query = "SELECT l.*, c.name as category_name, u.username
              FROM listings l
              LEFT JOIN categories c ON l.category_id = c.id
              LEFT JOIN users u ON l.user_id = u.id
              WHERE l.city_id = :city_id
              AND l.neighborhood = :neighborhood
              AND l.status = 'active'
              ORDER BY l.created_at DESC
              LIMIT 50";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':city_id', $city['id']);
    $stmt->bindParam(':neighborhood', $neighborhood);
    $stmt->execute();
    $listings = $stmt->fetchAll();
}

include 'views/header.php';
?>

<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">

<style>
.neighborhood-header {
    background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
}

.breadcrumb {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    margin-bottom: 1rem;
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.8);
}

.breadcrumb a {
    color: white;
    text-decoration: none;
}

.breadcrumb a:hover {
    text-decoration: underline;
}

.neighborhoods-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.neighborhood-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    color: var(--text-white);
}

.neighborhood-card:hover {
    border-color: var(--primary-blue);
    transform: translateY(-3px);
    background: rgba(66, 103, 245, 0.1);
}

.neighborhood-card.active {
    border-color: var(--primary-blue);
    background: rgba(66, 103, 245, 0.2);
}

.neighborhood-icon {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.neighborhood-name {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.neighborhood-count {
    font-size: 0.9rem;
    color: var(--text-gray);
}

.listings-container {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 2rem;
}

.listing-item {
    background: rgba(66, 103, 245, 0.05);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    transition: all 0.3s;
}

.listing-item:hover {
    border-color: var(--primary-blue);
    background: rgba(66, 103, 245, 0.1);
}

.listing-title {
    font-size: 1.2rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.listing-title a {
    color: var(--text-white);
    text-decoration: none;
}

.listing-title a:hover {
    color: var(--primary-blue);
}

.listing-meta {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    font-size: 0.9rem;
    color: var(--text-gray);
    margin-top: 0.5rem;
}

.map-container {
    width: 100%;
    height: 400px;
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-gray);
}

@media (max-width: 768px) {
    .neighborhoods-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-content">
    <div class="container">
        <div class="neighborhood-header">
            <div class="breadcrumb">
                <a href="/">Home</a>
                <span>›</span>
                <a href="/city.php?location=<?php echo $city['slug']; ?>">
                    <?php echo htmlspecialchars($city['name']); ?>, <?php echo $city['state_abbr']; ?>
                </a>
                <?php if($neighborhood): ?>
                <span>›</span>
                <span><?php echo htmlspecialchars($neighborhood); ?></span>
                <?php endif; ?>
            </div>
            
            <h1 style="margin-bottom: 0.5rem;">
                🏘️ <?php echo $neighborhood ? htmlspecialchars($neighborhood) : 'Browse by Neighborhood'; ?>
            </h1>
            <p style="opacity: 0.9;">
                <?php echo htmlspecialchars($city['name']); ?>, <?php echo $city['state_abbr']; ?>
            </p>
        </div>

        <?php if(!$neighborhood): ?>
        <!-- Neighborhoods Grid -->
        <?php if(count($neighborhoods) > 0): ?>
        <h2 style="margin-bottom: 1.5rem;">Select a Neighborhood</h2>
        <div class="neighborhoods-grid">
            <?php foreach($neighborhoods as $n): ?>
            <?php
            // Get listing count for neighborhood
            $query = "SELECT COUNT(*) as count FROM listings 
                      WHERE city_id = :city_id AND neighborhood = :neighborhood AND status = 'active'";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':city_id', $city['id']);
            $stmt->bindParam(':neighborhood', $n['name']);
            $stmt->execute();
            $count = $stmt->fetch()['count'];
            ?>
            <a href="?city=<?php echo $city['slug']; ?>&neighborhood=<?php echo urlencode($n['name']); ?>" 
               class="neighborhood-card">
                <div class="neighborhood-icon">📍</div>
                <div class="neighborhood-name"><?php echo htmlspecialchars($n['name']); ?></div>
                <div class="neighborhood-count"><?php echo $count; ?> listings</div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <h3>No Neighborhoods Defined</h3>
            <p>Neighborhoods haven't been set up for <?php echo htmlspecialchars($city['name']); ?> yet.</p>
            <a href="/city.php?location=<?php echo $city['slug']; ?>" class="btn-primary" style="margin-top: 1rem;">
                Browse All Listings
            </a>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- Listings for Selected Neighborhood -->
        <div style="margin-bottom: 2rem;">
            <a href="?city=<?php echo $city['slug']; ?>" class="btn-secondary">
                ← Back to All Neighborhoods
            </a>
        </div>

        <?php if(count($listings) > 0): ?>
        <div class="listings-container">
            <h2 style="margin-bottom: 1.5rem;">
                Listings in <?php echo htmlspecialchars($neighborhood); ?> 
                <span style="color: var(--text-gray); font-size: 1rem; font-weight: normal;">
                    (<?php echo count($listings); ?>)
                </span>
            </h2>

            <?php foreach($listings as $listing): ?>
            <div class="listing-item">
                <div class="listing-title">
                    <a href="/listing.php?id=<?php echo $listing['id']; ?>">
                        <?php echo htmlspecialchars($listing['title']); ?>
                    </a>
                </div>
                <div style="color: var(--text-gray); margin-bottom: 0.5rem;">
                    <?php echo htmlspecialchars(substr($listing['description'], 0, 150)); ?>...
                </div>
                <div class="listing-meta">
                    <span>📂 <?php echo htmlspecialchars($listing['category_name']); ?></span>
                    <span>👤 <?php echo htmlspecialchars($listing['username']); ?></span>
                    <span>📍 <?php echo htmlspecialchars($listing['neighborhood']); ?></span>
                    <span>🕐 <?php echo date('M j, Y', strtotime($listing['created_at'])); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <h3>No Listings Found</h3>
            <p>There are currently no active listings in <?php echo htmlspecialchars($neighborhood); ?>.</p>
            <a href="?city=<?php echo $city['slug']; ?>" class="btn-primary" style="margin-top: 1rem;">
                View Other Neighborhoods
            </a>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php include 'views/footer.php'; ?>