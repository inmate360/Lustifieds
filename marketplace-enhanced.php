<?php
// Enhanced Marketplace - Copy this to marketplace.php
session_start();
require_once 'config/database.php';
require_once 'classes/MediaContent.php';
require_once 'classes/CoinsSystem.php';

$database = new Database();
$db = $database->getConnection();
$mediaContent = new MediaContent($db);

// Get parameters
$page = (int)($_GET['page'] ?? 1);
$per_page = 24;
$content_type = $_GET['type'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$search = $_GET['search'] ?? '';
$min_price = isset($_GET['min_price']) ? (int)$_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : null;
$view_mode = $_GET['view'] ?? 'grid';

// Build filters
$filters = [];
if($content_type) $filters['content_type'] = $content_type;
if($search) $filters['search'] = $search;
if($min_price !== null) $filters['min_price'] = $min_price;
if($max_price !== null) $filters['max_price'] = $max_price;

$content_items = $mediaContent->browseContent($filters, $page, $per_page);

// User data
$coin_balance = 0;
$user_wishlist = [];
if(isset($_SESSION['user_id'])) {
    $coinsSystem = new CoinsSystem($db);
    $coin_balance = $coinsSystem->getBalance($_SESSION['user_id']);
    
    $wishlist_query = "SELECT content_id FROM content_wishlist WHERE user_id = ?";
    $stmt = $db->prepare($wishlist_query);
    $stmt->execute([$_SESSION['user_id']]);
    $user_wishlist = array_column($stmt->fetchAll(), 'content_id');
}

include 'views/header.php';
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/marketplace-enhanced.css">

<div class="hero"><div class="container"><h1>💎 Marketplace</h1><p>Exclusive content from creators</p><?php if(isset($_SESSION['user_id'])):?><div class="coin-card"><span>💰</span><div><small>Balance</small><strong><?php echo number_format($coin_balance);?> coins</strong></div><a href="/buy-coins.php">Buy</a></div><?php else:?><a href="/login.php" class="btn">Login</a><?php endif;?></div></div>
<div class="container"><div class="search"><form><input name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search);?>"><button><i class="bi bi-search"></i></button></form></div><div class="filters"><h3><i class="bi bi-funnel"></i> Filters</h3><form><div class="row"><div><label>Type</label><select name="type"><option value="">All</option><option value="photo" <?=$content_type==='photo'?'selected':''?>>📸 Photos</option><option value="video" <?=$content_type==='video'?'selected':''?>>🎥 Videos</option></select></div><div><label>Min</label><input type="number" name="min_price" value="<?=$min_price??''?>"></div><div><label>Max</label><input type="number" name="max_price" value="<?=$max_price??''?>"></div><div><label>Sort</label><select name="sort"><option value="newest" <?=$sort==='newest'?'selected':''?>>Newest</option><option value="popular" <?=$sort==='popular'?'selected':''?>>Popular</option></select></div></div><button type="submit">Apply</button><a href="marketplace.php">Clear</a></form></div><div class="bar"><span>Showing <?=count($content_items)?> items</span><div class="toggle"><button class="<?=$view_mode==='grid'?'active':''?>" onclick="changeView('grid')"><i class="bi bi-grid"></i></button><button class="<?=$view_mode==='list'?'active':''?>" onclick="changeView('list')"><i class="bi bi-list"></i></button></div></div><?php if(empty($content_items)):?><div class="empty">🔍<h3>No content</h3><a href="marketplace.php">View All</a></div><?php else:?><div class="<?=$view_mode?>"><?php foreach($content_items as $item):?><div class="card" onclick="location.href='/view-content.php?id=<?=$item['id']?>'"><div class="thumb-wrap"><div class="creator"><div class="avatar"><?=strtoupper(substr($item['creator_name']??'U',0,1))?></div><span><?=htmlspecialchars($item['creator_name']??'Unknown')?></span><?php if($item['is_verified']??0):?>✓<?php endif;?></div><?php if(isset($_SESSION['user_id'])):?><button class="wishlist <?=in_array($item['id'],$user_wishlist)?'active':''?>" onclick="event.stopPropagation();toggleWishlist(<?=$item['id']?>,this)"><i class="bi bi-heart<?=in_array($item['id'],$user_wishlist)?'-fill':''?>"></i></button><?php endif;?><?php if($item['thumbnail']??0):?><img src="<?=htmlspecialchars($item['thumbnail'])?>" class="thumb <?=($item['blur_preview']??0)?'blur':''?>"><?php else:?><div class="thumb"><?=($item['content_type']??'')==='video'?'🎥':'📷'?></div><?php endif;?><?php if(!($item['is_free']??1)):?><div class="lock">🔒<div class="price">💰 <?=number_format($item['price']??0)?></div></div><?php else:?><div class="free">FREE</div><?php endif;?></div><div class="info"><h4><?=htmlspecialchars($item['title']??'Untitled')?></h4><?php if($item['description']??0):?><p><?=htmlspecialchars($item['description'])?></p><?php endif;?><div class="stats"><span><i class="bi bi-eye"></i> <?=number_format($item['view_count']??0)?></span><span><i class="bi bi-heart"></i> <?=number_format($item['like_count']??0)?></span><span><i class="bi bi-cart"></i> <?=number_format($item['purchase_count']??0)?></span></div></div></div><?php endforeach;?></div><?php endif;?></div>
<script>function changeView(m){location.href='?view='+m}function toggleWishlist(id,btn){fetch('/api/wishlist.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({content_id:id,action:'toggle'})}).then(r=>r.json()).then(d=>{if(d.success){btn.classList.toggle('active');btn.querySelector('i').className='bi bi-heart'+(btn.classList.contains('active')?'-fill':'')}})}</script>
<?php include 'views/footer.php';?>