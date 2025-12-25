<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Location.php';
require_once 'classes/Category.php';
require_once 'classes/Listing.php';
require_once 'classes/FeaturedAd.php';
require_once 'classes/ImageUpload.php';

if(!isset($_GET['city']) || !isset($_GET['category'])) {
    header('Location: choose-location.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$location = new Location($db);
$category = new Category($db);
$listing = new Listing($db);
$featuredAd = new FeaturedAd($db);
$imageUpload = new ImageUpload($db);

$city = $location->getCityBySlug($_GET['city']);
$cat = $category->getBySlug($_GET['category']);

if(!$city || !$cat) {
    header('Location: choose-location.php');
    exit();
}

// Get listings for this city and category
$query = "SELECT l.* 
          FROM listings l
          WHERE l.city_id = :city_id 
          AND l.category_id = :cat_id 
          AND l.status = 'active'
          AND l.expires_at > NOW()
          ORDER BY l.created_at DESC
          LIMIT 50";
$stmt = $db->prepare($query);
$stmt->bindParam(':city_id', $city['id']);
$stmt->bindParam(':cat_id', $cat['id']);
$stmt->execute();
$listings = $stmt->fetchAll();

// Get featured listings
$featured_listings = [];
try {
    $featured_listings = $featuredAd->getActiveFeaturedListings($cat['id'], 3);
} catch(Exception $e) {
    error_log("Featured listings error: " . $e->getMessage());
}

include 'views/header.php';
?>

<div class="browse-page">
    <div class="container-narrow">
        <div style="margin-bottom: 2rem;">
            <a href="city.php?location=<?php echo $city['slug']; ?>" class="breadcrumb">← Back to Categories</a>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
                <h1><?php echo htmlspecialchars($cat['name']); ?> in <?php echo htmlspecialchars($city['name']); ?></h1>
                <?php if(isset($_SESSION['user_id'])): ?>
                <a href="create-listing.php?city=<?php echo $city['slug']; ?>&category=<?php echo $cat['slug']; ?>" class="btn-primary">+ Post Ad</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if(count($featured_listings) > 0): ?>
        <div class="featured-listings">
            <h3>⭐ Featured Ads</h3>
            <?php foreach($featured_listings as $item): ?>
            <div class="listing-item featured">
                <div class="featured-badge">FEATURED</div>
                <a href="listing.php?id=<?php echo $item['id']; ?>" onclick="trackFeaturedClick(<?php echo $item['id']; ?>)">
                    <?php if(!empty($item['primary_image'])): ?>
                    <img src="<?php echo htmlspecialchars($item['primary_image']); ?>" 
                         alt="<?php echo htmlspecialchars($item['title']); ?>" 
                         style="width: 100%; height: 200px; object-fit: cover; border-radius: 10px; margin-bottom: 1rem;">
                    <?php endif; ?>
                    <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                    <p><?php echo htmlspecialchars(substr($item['description'], 0, 200)); ?>...</p>
                    <div class="listing-meta">
                        <span>📍 <?php echo htmlspecialchars($item['location']); ?></span>
                        <span>🕐 <?php echo date('M j, g:i A', strtotime($item['created_at'])); ?></span>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="listings-list">
            <h3>All Listings (<?php echo count($listings); ?>)</h3>
            <?php if(count($listings) > 0): ?>
                <?php foreach($listings as $item): ?>
                <?php 
                // Get primary image
                $primary_img = $imageUpload->getPrimaryImage($item['id']);
                ?>
                <div class="listing-item">
                    <a href="listing.php?id=<?php echo $item['id']; ?>">
                        <?php if($primary_img): ?>
                        <img src="<?php echo htmlspecialchars($primary_img['file_path']); ?>" 
                             alt="<?php echo htmlspecialchars($item['title']); ?>" 
                             style="width: 100%; height: 200px; object-fit: cover; border-radius: 10px; margin-bottom: 1rem;">
                        <?php endif; ?>
                        <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                        <p><?php echo htmlspecialchars(substr($item['description'], 0, 200)); ?>...</p>
                        <div class="listing-meta">
                            <span>📍 <?php echo htmlspecialchars($item['location']); ?></span>
                            <span>🕐 <?php echo date('M j, g:i A', strtotime($item['created_at'])); ?></span>
                            <span>👁️ <?php echo $item['views']; ?> views</span>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-listings">
                    <p>No listings in this category yet. Be the first to post!</p>
                    <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="create-listing.php?city=<?php echo $city['slug']; ?>&category=<?php echo $cat['slug']; ?>" class="btn-primary">+ Create Listing</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function trackFeaturedClick(listingId) {
    fetch('track-featured-click.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'listing_id=' + listingId
    }).catch(function(error) {
        console.log('Track click error:', error);
    });
}
</script>

<?php include 'views/footer.php'; ?>