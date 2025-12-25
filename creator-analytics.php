<?php
session_start();
require_once 'config/database.php';
require_once 'classes/MediaContent.php';
require_once 'classes/CoinsSystem.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check if user is a creator
$query = "SELECT * FROM creator_settings WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$creator_settings = $stmt->fetch();

if(!$creator_settings || !$creator_settings['is_creator']) {
    header('Location: become-creator.php');
    exit();
}

$coinsSystem = new CoinsSystem($db);

// Get date range
$range = $_GET['range'] ?? '30';
$start_date = date('Y-m-d', strtotime("-{$range} days"));

// Get earnings stats
$query = "SELECT 
    SUM(CASE WHEN type = 'earn' THEN amount ELSE 0 END) as total_earned,
    COUNT(DISTINCT DATE(created_at)) as active_days,
    COUNT(*) as total_transactions
    FROM coin_transactions 
    WHERE user_id = :user_id 
    AND type IN ('earn', 'tip')
    AND created_at >= :start_date";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->bindParam(':start_date', $start_date);
$stmt->execute();
$earnings_stats = $stmt->fetch();

// Get daily earnings
$query = "SELECT DATE(created_at) as date, SUM(amount) as daily_total
    FROM coin_transactions
    WHERE user_id = :user_id
    AND type IN ('earn', 'tip')
    AND created_at >= :start_date
    GROUP BY DATE(created_at)
    ORDER BY date ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->bindParam(':start_date', $start_date);
$stmt->execute();
$daily_earnings = $stmt->fetchAll();

// Get top content
$query = "SELECT mc.*, 
    COUNT(mp.id) as purchases,
    SUM(mp.price_paid) as revenue
    FROM media_content mc
    LEFT JOIN media_purchases mp ON mp.content_id = mc.id
    WHERE mc.creator_id = :user_id
    AND mc.status = 'published'
    GROUP BY mc.id
    ORDER BY revenue DESC
    LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$top_content = $stmt->fetchAll();

// Get subscriber growth
$query = "SELECT DATE(start_date) as date, COUNT(*) as new_subs
    FROM creator_subscriptions
    WHERE creator_id = :user_id
    AND start_date >= :start_date
    GROUP BY DATE(start_date)
    ORDER BY date ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->bindParam(':start_date', $start_date);
$stmt->execute();
$sub_growth = $stmt->fetchAll();

$coin_balance = $coinsSystem->getBalance($_SESSION['user_id']);

include 'views/header.php';
?>

<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">
<link rel="stylesheet" href="/assets/css/light-theme.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
.analytics-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 20px;
}

.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

.stat-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 20px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary-blue);
}

.stat-value {
    font-size: 3rem;
    font-weight: 800;
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--text-gray);
    font-size: 1rem;
}

.chart-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.filter-tabs {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid var(--border-color);
}

.filter-tab {
    padding: 1rem 2rem;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    color: var(--text-gray);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.filter-tab:hover {
    color: var(--primary-blue);
}

.filter-tab.active {
    color: var(--primary-blue);
    border-bottom-color: var(--primary-blue);
}

.content-table {
    width: 100%;
    border-collapse: collapse;
}

.content-table th,
.content-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.content-table th {
    color: var(--text-gray);
    font-weight: 600;
}
</style>

<div class="page-content">
    <div class="analytics-container">
        
        <div class="card" style="background: linear-gradient(135deg, #4267F5, #1D9BF0); color: white; margin-bottom: 2rem;">
            <div style="padding: 2rem;">
                <h1 style="margin: 0 0 0.5rem;">📊 Creator Analytics</h1>
                <p style="opacity: 0.9; margin: 0;">Track your performance and earnings</p>
            </div>
        </div>
        
        <!-- Time Range Filter -->
        <div class="filter-tabs">
            <a href="?range=7" class="filter-tab <?php echo $range == '7' ? 'active' : ''; ?>">Last 7 Days</a>
            <a href="?range=30" class="filter-tab <?php echo $range == '30' ? 'active' : ''; ?>">Last 30 Days</a>
            <a href="?range=90" class="filter-tab <?php echo $range == '90' ? 'active' : ''; ?>">Last 90 Days</a>
            <a href="?range=365" class="filter-tab <?php echo $range == '365' ? 'active' : ''; ?>">Last Year</a>
        </div>
        
        <!-- Stats Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($coin_balance, 0); ?></div>
                <div class="stat-label">💰 Current Balance</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($earnings_stats['total_earned'] ?? 0, 0); ?></div>
                <div class="stat-label">💵 Total Earned</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($earnings_stats['total_transactions'] ?? 0); ?></div>
                <div class="stat-label">🔢 Transactions</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo round(($earnings_stats['total_earned'] ?? 0) / max($earnings_stats['active_days'] ?? 1, 1), 0); ?></div>
                <div class="stat-label">📈 Avg Daily Earnings</div>
            </div>
        </div>
        
        <!-- Earnings Chart -->
        <div class="chart-card">
            <h3 style="margin-bottom: 2rem;">💰 Earnings Over Time</h3>
            <canvas id="earningsChart" height="100"></canvas>
        </div>
        
        <!-- Subscriber Growth -->
        <?php if(!empty($sub_growth)): ?>
        <div class="chart-card">
            <h3 style="margin-bottom: 2rem;">⭐ Subscriber Growth</h3>
            <canvas id="subscribersChart" height="100"></canvas>
        </div>
        <?php endif; ?>
        
        <!-- Top Performing Content -->
        <div class="card">
            <h3 style="margin-bottom: 2rem;">🔥 Top Performing Content</h3>
            <div style="overflow-x: auto;">
                <table class="content-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Sales</th>
                            <th>Revenue</th>
                            <th>Views</th>
                            <th>Likes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($top_content as $content): ?>
                        <tr>
                            <td>
                                <a href="/view-content.php?id=<?php echo $content['id']; ?>" style="color: var(--primary-blue);">
                                    <?php echo htmlspecialchars($content['title']); ?>
                                </a>
                            </td>
                            <td><?php echo number_format($content['purchases']); ?></td>
                            <td style="color: var(--success-green); font-weight: 700;">
                                <?php echo number_format($content['revenue'] ?? 0); ?> coins
                            </td>
                            <td><?php echo number_format($content['view_count']); ?></td>
                            <td><?php echo number_format($content['like_count']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
</div>

<script>
// Earnings Chart
const earningsData = <?php echo json_encode(array_column($daily_earnings, 'daily_total')); ?>;
const earningsLabels = <?php echo json_encode(array_map(function($d) { return date('M j', strtotime($d['date'])); }, $daily_earnings)); ?>;

new Chart(document.getElementById('earningsChart'), {
    type: 'line',
    data: {
        labels: earningsLabels,
        datasets: [{
            label: 'Daily Earnings (coins)',
            data: earningsData,
            borderColor: '#4267F5',
            backgroundColor: 'rgba(66, 103, 245, 0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                labels: { color: 'rgba(255,255,255,0.7)' }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { color: 'rgba(255,255,255,0.7)' },
                grid: { color: 'rgba(255,255,255,0.1)' }
            },
            x: {
                ticks: { color: 'rgba(255,255,255,0.7)' },
                grid: { color: 'rgba(255,255,255,0.1)' }
            }
        }
    }
});

<?php if(!empty($sub_growth)): ?>
// Subscribers Chart
const subsData = <?php echo json_encode(array_column($sub_growth, 'new_subs')); ?>;
const subsLabels = <?php echo json_encode(array_map(function($d) { return date('M j', strtotime($d['date'])); }, $sub_growth)); ?>;

new Chart(document.getElementById('subscribersChart'), {
    type: 'bar',
    data: {
        labels: subsLabels,
        datasets: [{
            label: 'New Subscribers',
            data: subsData,
            backgroundColor: '#1D9BF0'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                labels: { color: 'rgba(255,255,255,0.7)' }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { color: 'rgba(255,255,255,0.7)', stepSize: 1 },
                grid: { color: 'rgba(255,255,255,0.1)' }
            },
            x: {
                ticks: { color: 'rgba(255,255,255,0.7)' },
                grid: { color: 'rgba(255,255,255,0.1)' }
            }
        }
    }
});
<?php endif; ?>
</script>

<?php include 'views/footer.php'; ?>