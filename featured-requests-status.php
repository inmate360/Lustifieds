<?php
session_start();
require_once 'config/database.php';
require_once 'classes/FeaturedAd.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$featuredAd = new FeaturedAd($db);

$requests = $featuredAd->getUserRequests($_SESSION['user_id']);

include 'views/header.php';
?>

<div class="container" style="margin: 2rem auto;">
    <h2>🌟 My Featured Ad Requests</h2>
    
    <?php if(isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if(count($requests) > 0): ?>
    <div class="requests-table">
        <table>
            <thead>
                <tr>
                    <th>Listing</th>
                    <th>Duration</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Active Until</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($requests as $req): ?>
                <tr>
                    <td>
                        <a href="listing.php?id=<?php echo $req['listing_id']; ?>">
                            <?php echo htmlspecialchars($req['title']); ?>
                        </a>
                    </td>
                    <td><?php echo $req['duration_days']; ?> days</td>
                    <td>$<?php echo number_format($req['price'], 2); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $req['status']; ?>">
                            <?php echo ucfirst($req['status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('M j, Y', strtotime($req['requested_at'])); ?></td>
                    <td>
                        <?php if($req['ends_at']): ?>
                            <?php echo date('M j, Y', strtotime($req['ends_at'])); ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if($req['review_notes']): ?>
                <tr class="notes-row">
                    <td colspan="6">
                        <strong>Notes:</strong> <?php echo htmlspecialchars($req['review_notes']); ?>
                        <?php if($req['reviewer_name']): ?>
                            <em>(by <?php echo htmlspecialchars($req['reviewer_name']); ?>)</em>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="no-data">
        <p>You haven't requested any featured ads yet.</p>
        <a href="my-listings.php" class="btn-primary">View My Listings</a>
    </div>
    <?php endif; ?>
</div>

<style>
.requests-table {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-top: 2rem;
}

.requests-table table {
    width: 100%;
    border-collapse: collapse;
}

.requests-table th,
.requests-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #f0f0f0;
}

.requests-table th {
    background: #f8f9fa;
    font-weight: 600;
}

.requests-table tr:hover {
    background: #f8f9fa;
}

.status-badge {
    padding: 0.3rem 0.8rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: bold;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-approved {
    background: #d4edda;
    color: #155724;
}

.status-rejected {
    background: #f8d7da;
    color: #721c24;
}

.status-expired {
    background: #e2e3e5;
    color: #383d41;
}

.notes-row td {
    background: #f8f9fa;
    font-size: 0.9rem;
    color: #666;
}

.no-data {
    text-align: center;
    padding: 3rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-top: 2rem;
}
</style>

<?php include 'views/footer.php'; ?>